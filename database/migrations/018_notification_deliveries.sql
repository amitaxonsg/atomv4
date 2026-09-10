SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS notification_deliveries (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  notification_event_id BIGINT UNSIGNED NOT NULL,
  alert_recipient_id BIGINT UNSIGNED NOT NULL,
  email_queue_id BIGINT UNSIGNED NULL,

  status VARCHAR(50) NOT NULL DEFAULT 'queued',

  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,

  UNIQUE KEY uq_notification_delivery (
    notification_event_id,
    alert_recipient_id
  ),

  INDEX idx_notification_delivery_event (
    notification_event_id
  ),

  INDEX idx_notification_delivery_recipient (
    alert_recipient_id
  ),

  INDEX idx_notification_delivery_email_queue (
    email_queue_id
  ),

  INDEX idx_notification_delivery_status (
    status,
    created_at
  ),

  CONSTRAINT fk_notification_delivery_event
    FOREIGN KEY (notification_event_id)
    REFERENCES notification_events(id)
    ON DELETE CASCADE,

  CONSTRAINT fk_notification_delivery_recipient
    FOREIGN KEY (alert_recipient_id)
    REFERENCES admin_alert_recipients(id)
    ON DELETE CASCADE,

  CONSTRAINT fk_notification_delivery_email_queue
    FOREIGN KEY (email_queue_id)
    REFERENCES email_queue(id)
    ON DELETE SET NULL
) ENGINE=InnoDB;
