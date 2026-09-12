<?php
// SPDX-License-Identifier: BSD-3-Clause
// TigerImage 0002 — record WHICH credential paid for an image (TIGER-100 / TIGER-102).
//
// Without this, spend can only be summed per org, so a scoped token handed to an agent can spend the
// whole organisation's budget. tiger-core 1.5.21+ carries credential_id on the identity; this is
// where that attribution lands so a per-token ceiling can be enforced.
//
// NULL means a session user, not "unknown" — a human in the admin UI is bound by the org cap alone.
return [
    'up' => [
        "ALTER TABLE `tigerimage_image`
            ADD COLUMN `credential_id` CHAR(36) NULL AFTER `org_id`,
            ADD KEY `ix_ti_image_credential` (`credential_id`, `created_at`)",
    ],
    'down' => [
        "ALTER TABLE `tigerimage_image`
            DROP KEY `ix_ti_image_credential`,
            DROP COLUMN `credential_id`",
    ],
];
