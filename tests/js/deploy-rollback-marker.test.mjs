import assert from "node:assert/strict";
import fs from "node:fs";
import test from "node:test";

const deploy = fs.readFileSync("deploy/update-v3-apache-staging.sh", "utf8");

test("V4 deploy rollback restores the deployed commit marker from the actual previous release", () => {
  assert.match(deploy, /PREVIOUS_RELEASE="\$\(readlink -f "\$APP_ROOT\/current"/);
  assert.match(deploy, /PREVIOUS_COMMIT_PREFIX="\$\{PREVIOUS_RELEASE##-\}"/);
  assert.match(deploy, /git rev-parse "\$\{PREVIOUS_COMMIT_PREFIX\}\^\{commit\}"/);
  assert.match(deploy, /printf '%s\\n' "\$PREVIOUS_COMMIT" > "\$APP_ROOT\/deployed-commit\.txt"/);
  assert.match(deploy, /ln -sfn "\$PREVIOUS_RELEASE" "\$APP_ROOT\/current\.rollback"[\s\S]*deployed-commit\.txt/);
});
