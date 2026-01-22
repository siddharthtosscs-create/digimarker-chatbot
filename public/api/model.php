<?php
declare(strict_types=1);

// Backwards-compatible alias (some deployments/docs mistakenly use model.php).
// This file simply delegates to models.php.
require __DIR__ . DIRECTORY_SEPARATOR . 'models.php';
