<?php
declare(strict_types=1);

namespace AtomGlobal\Services;

use AtomGlobal\Database;
use AtomGlobal\Mail\MailQueue;

final class PasswordResetService
{
    public function __construct(
        private Database $db,
        private MailQueue $mailQueue,
        private array $config,
    ) {}

    public function request(string $email): ?int
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
        $user = $this->db->fetch('SELECT id, email, display_name FROM admin_users WHERE email = ? AND is_active = 1 LIMIT 1', [$email]);
        if (!$user) return null;

        $token = bin2hex(random_bytes(32));
        $this->db->transaction(function () use ($user, $token) {
            $this->db->execute('DELETE FROM password_reset_tokens WHERE admin_user_id = ? OR expires_at < NOW() OR used_at IS NOT NULL', [$user['id']]);
            $this->db->execute('INSERT INTO password_reset_tokens (admin_user_id, token_hash, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 60 MINUTE), NOW())', [$user['id'], hash('sha256', $token)]);
        });
        $resetUrl = rtrim((string) $this->config['url'], '/') . '/admin/reset-password?token=' . rawurlencode($token);
        $queueId = $this->mailQueue->enqueue('password_reset', $user['email'], [
            'adminName' => $user['display_name'],
            'resetUrl' => $resetUrl,
            'expiresMinutes' => 60,
        ]);
        $this->db->execute('INSERT INTO audit_logs (admin_user_id, action, entity_type, entity_id, created_at) VALUES (?, ?, ?, ?, NOW())', [$user['id'], 'admin.password_reset_requested', 'admin_user', (string) $user['id']]);
        return $queueId;
    }

    public function confirm(string $token, string $password): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) throw new \InvalidArgumentException('The reset link is invalid or expired.');
        $this->validatePassword($password);
        $row = $this->db->fetch('SELECT pr.id reset_id, pr.admin_user_id FROM password_reset_tokens pr JOIN admin_users u ON u.id = pr.admin_user_id AND u.is_active = 1 WHERE pr.token_hash = ? AND pr.expires_at > NOW() AND pr.used_at IS NULL LIMIT 1 FOR UPDATE', [hash('sha256', $token)]);
        if (!$row) throw new \InvalidArgumentException('The reset link is invalid or expired.');

        $this->db->transaction(function () use ($row, $password) {
            $this->db->execute('UPDATE admin_users SET password_hash = ?, failed_login_attempts = 0, locked_until = NULL, session_version = session_version + 1, updated_at = NOW() WHERE id = ?', [password_hash($password, PASSWORD_ARGON2ID), $row['admin_user_id']]);
            $this->db->execute('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?', [$row['reset_id']]);
            $this->db->execute('DELETE FROM password_reset_tokens WHERE admin_user_id = ? AND id <> ?', [$row['admin_user_id'], $row['reset_id']]);
            $this->db->execute('INSERT INTO audit_logs (admin_user_id, action, entity_type, entity_id, created_at) VALUES (?, ?, ?, ?, NOW())', [$row['admin_user_id'], 'admin.password_reset_completed', 'admin_user', (string) $row['admin_user_id']]);
        });
    }

    private function validatePassword(string $password): void
    {
        if (strlen($password) < 12 || !preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password) || !preg_match('/\d/', $password) || !preg_match('/[^a-zA-Z0-9]/', $password)) {
            throw new \InvalidArgumentException('Use at least 12 characters with upper-case, lower-case, number and symbol.');
        }
    }
}
