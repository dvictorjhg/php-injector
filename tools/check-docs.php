<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$document = new DOMDocument();
$previousErrors = libxml_use_internal_errors(true);
$document->loadHTML(file_get_contents($root . '/docs/index.html'));
libxml_clear_errors();
libxml_use_internal_errors($previousErrors);
$xpath = new DOMXPath($document);
$examples = $xpath->query('//pre/code[@data-example]');
$failures = 0;
$checked = 0;

$normalize = static fn (string $value): string => rtrim(str_replace("\r\n", "\n", $value), "\n");

$verify = static function (string $id, string $code, string $expected) use (
    $root,
    $normalize,
    &$failures,
    &$checked
): void {
    $temporary = tempnam($root, '.docs-example-');
    try {
        file_put_contents($temporary, $code);
        $process = proc_open(
            [PHP_BINARY, '-d', 'xdebug.mode=off', '-d', 'display_errors=stderr', $temporary],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $root
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start PHP.');
        }
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);
        ++$checked;
        if ($status !== 0 || $errors !== '' || $normalize($output) !== $normalize($expected)) {
            ++$failures;
            fwrite(STDERR, "FAIL $id\nExpected:\n$expected\nActual:\n$output\n$errors\n");
        } else {
            echo "PASS $id\n";
        }
    } finally {
        unlink($temporary);
    }
};

foreach ($examples as $example) {
    $id = $example->getAttribute('data-example');
    $outputs = $xpath->query('//code[@data-output-for]');
    $expected = null;
    foreach ($outputs as $output) {
        if ($output->getAttribute('data-output-for') === $id) {
            $expected = $output->textContent;
            break;
        }
    }
    if ($expected === null) {
        throw new RuntimeException("No expected output for $id.");
    }
    $verify('HTML: ' . $id, $example->textContent, $expected);
}

$readme = file_get_contents($root . '/README.md');
preg_match_all('/```php\R(?<code>.*?)```(?<after>.*?)(?=```php|\z)/s', $readme, $blocks, PREG_SET_ORDER);
foreach ($blocks as $index => $block) {
    if (preg_match('/```text\R(?<output>.*?)```/s', $block['after'], $output) !== 1) {
        continue;
    }
    if (str_contains($block['code'], '->listen(')) {
        continue;
    }
    $verify('README: ' . ($index + 1), $block['code'], $output['output']);
}

if ($checked === 0) {
    throw new RuntimeException('No documentation examples found.');
}
echo "$checked examples checked; $failures failures.\n";
exit($failures === 0 ? 0 : 1);
