<?php

declare(strict_types=1);

/**
 * Writes the files docs/INSTALLATION.md tells a host application to create, into a target app.
 *
 * The point is that the installation test installs from the *documentation*, not from a second copy
 * of it kept in the test. A snippet that is wrong, stale or incomplete therefore fails CI, which is
 * the only thing that reliably stops an installation guide drifting away from the code.
 *
 * Usage: php extract_documented_files.php <path-to-INSTALLATION.md> <path-to-target-app>
 */
final class DocumentedFileExtractor
{
    /** Blocks whose content is illustrative rather than a whole file, and is applied by install.sh. */
    private const array PARTIAL = ['config/bundles.php'];

    /** @return array<string, string> file path => contents, blocks for the same path concatenated */
    public function extract(string $markdown): array
    {
        $files = [];

        // ```<lang>\n# <path>\n\n<body>\n```
        preg_match_all('/^```(php|yaml)\n# (\S+)\n(.*?)^```$/ms', $markdown, $matches, PREG_SET_ORDER);

        foreach ($matches as [, $language, $path, $body]) {
            if (in_array($path, self::PARTIAL, true)) {
                continue;
            }

            $body = trim($body);

            if (isset($files[$path])) {
                // The docs split one file across several steps - the resource config is introduced
                // in step 3 and added to in steps 5 and 6. Concatenating keeps the file whole, and
                // the top-level YAML keys do not collide.
                $files[$path] .= "\n\n" . $body;

                continue;
            }

            // The PHP snippets start at `namespace`, because repeating the preamble in every block
            // would bury the part a reader actually needs.
            $files[$path] = 'php' === $language ? "<?php\n\ndeclare(strict_types=1);\n\n" . $body : $body;
        }

        return $files;
    }

    /** @return list<string> the paths written */
    public function writeInto(string $markdownPath, string $targetDirectory): array
    {
        $markdown = file_get_contents($markdownPath);

        if (false === $markdown) {
            throw new RuntimeException(sprintf('Cannot read %s.', $markdownPath));
        }

        $written = [];

        foreach ($this->extract($markdown) as $path => $contents) {
            $absolute = rtrim($targetDirectory, '/') . '/' . $path;

            if (!is_dir(dirname($absolute)) && !mkdir(dirname($absolute), 0o777, true) && !is_dir(dirname($absolute))) {
                throw new RuntimeException(sprintf('Cannot create %s.', dirname($absolute)));
            }

            file_put_contents($absolute, $contents . "\n");
            $written[] = $path;
        }

        return $written;
    }
}

$markdownPath = $argv[1] ?? null;
$targetDirectory = $argv[2] ?? null;

if (null === $markdownPath || null === $targetDirectory) {
    fwrite(STDERR, "Usage: php extract_documented_files.php <INSTALLATION.md> <target-app>\n");

    exit(1);
}

$written = (new DocumentedFileExtractor())->writeInto($markdownPath, $targetDirectory);

if ([] === $written) {
    fwrite(STDERR, "No documented files found - has the format of INSTALLATION.md changed?\n");

    exit(1);
}

foreach ($written as $path) {
    echo 'wrote ', $path, "\n";
}
