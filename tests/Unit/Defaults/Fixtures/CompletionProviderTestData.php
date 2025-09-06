<?php

declare(strict_types=1);

namespace Mcp\Server\Tests\Unit\Defaults\Fixtures;

final class CompletionProviderTestData
{
    public static function basicStringList(): array
    {
        return ['apple', 'banana', 'cherry'];
    }

    public static function fruitList(): array
    {
        return ['apple', 'banana', 'cherry', 'apricot', 'avocado'];
    }

    public static function applicationList(): array
    {
        return ['application', 'apply', 'appreciate'];
    }

    public static function mixedCaseList(): array
    {
        return ['Apple', 'banana', 'cherry'];
    }

    public static function duplicateValuesList(): array
    {
        return ['apple', 'banana', 'apple', 'cherry'];
    }

    public static function numericStringsList(): array
    {
        return ['123', '124', '234', '345'];
    }

    public static function emailList(): array
    {
        return ['test@email.com', 'test@domain.org', 'admin@site.net'];
    }

    public static function listWithEmptyStrings(): array
    {
        return ['', 'apple', 'banana'];
    }

    public static function listWithWhitespace(): array
    {
        return [' apple', 'apple ', ' banana ', 'cherry'];
    }

    public static function unicodeList(): array
    {
        return ['café', 'naïve', 'résumé', 'piñata'];
    }

    public static function emptyList(): array
    {
        return [];
    }

    public static function singleItemList(): array
    {
        return ['single_item'];
    }

    public static function largeDataset(int $size = 10000): array
    {
        $values = [];
        for ($i = 0; $i < $size; $i++) {
            $values[] = "item_{$i}";
        }
        return $values;
    }

    public static function specialCharactersList(): array
    {
        return [
            'file.txt',
            'file_name.php',
            'file-with-dashes.js',
            'file with spaces.doc',
            'file@symbol.ext',
            'file#hash.tmp',
            'file$dollar.bin',
            'file%percent.log',
        ];
    }

    public static function pathList(): array
    {
        return [
            '/home/user/documents',
            '/home/user/downloads',
            '/var/log/application',
            '/var/lib/database',
            '/usr/local/bin',
        ];
    }

    public static function versionList(): array
    {
        return [
            '1.0.0',
            '1.0.1',
            '1.1.0',
            '2.0.0',
            '2.0.0-alpha',
            '2.0.0-beta',
            '10.0.0',
        ];
    }
}
