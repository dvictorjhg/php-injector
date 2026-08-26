<?php

declare(strict_types=1);

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php tools/check-coverage.php <clover-file> <minimum-percent>\n");
    exit(1);
}

[, $cloverFile, $minimumPercent] = $argv;

if (!is_file($cloverFile)) {
    fwrite(STDERR, "Coverage file not found: {$cloverFile}\n");
    exit(1);
}

if (!is_numeric($minimumPercent)) {
    fwrite(STDERR, "Minimum coverage must be numeric.\n");
    exit(1);
}

$xml = simplexml_load_file($cloverFile);

if ($xml === false || !isset($xml->project->metrics)) {
    fwrite(STDERR, "Unable to read coverage metrics from {$cloverFile}\n");
    exit(1);
}

$metrics = $xml->project->metrics;
$statementCount = (int) $metrics['statements'];
$coveredStatementCount = (int) $metrics['coveredstatements'];
$coveragePercent = $statementCount === 0
    ? 100.0
    : ($coveredStatementCount / $statementCount) * 100;
$requiredCoverage = (float) $minimumPercent;

fwrite(
    STDOUT,
    sprintf(
        "Statement coverage: %.2f%% (%d/%d)\n",
        $coveragePercent,
        $coveredStatementCount,
        $statementCount
    )
);

if ($coveragePercent < $requiredCoverage) {
    fwrite(
        STDOUT,
        sprintf(
            "Coverage %.2f%% is below the required threshold of %.2f%%.\n",
            $coveragePercent,
            $requiredCoverage
        )
    );
    exit(1);
}

fwrite(
    STDOUT,
    sprintf(
        "Coverage threshold satisfied: %.2f%% >= %.2f%%.\n",
        $coveragePercent,
        $requiredCoverage
    )
);
