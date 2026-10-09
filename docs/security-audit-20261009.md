# Payment configuration audit - 2026-10-09

The MoMo/Web2M token appeared twice in momo.php in the initial published commit. The earlier scan missed the PHP array value and URL-embedded token.

Current source loads the token from momo-token.local.php, excluded by *.local.php. momo-token.example.php contains a placeholder only.

An exact-value scan of tracked worktree files found no matches for the three known local payment secrets (Thesieure, SePay, MoMo). The initial commit still contains the MoMo token. This check does not prove that all other possible secrets are absent.

Revoke or rotate the exposed token through Web2M. Updating source does not remove secrets from prior commits. Rewriting the published branch requires repository owner approval and coordination with existing clones. Never put real token values in audit notes or logs.
