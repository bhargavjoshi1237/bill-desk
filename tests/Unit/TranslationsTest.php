<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Every __() / trans_choice() string in app/ must have a Hindi translation,
 * with the same placeholders and plural forms.
 */
class TranslationsTest extends TestCase
{
    public function test_every_interface_string_has_a_hindi_translation(): void
    {
        $hindi = json_decode(file_get_contents(__DIR__.'/../../lang/hi.json'), true, flags: JSON_THROW_ON_ERROR);

        $missing = array_values(array_diff($this->interfaceStrings(), array_keys($hindi)));

        $this->assertSame([], $missing, 'Add these strings to lang/hi.json');
    }

    public function test_translations_keep_placeholders_and_plural_forms(): void
    {
        $hindi = json_decode(file_get_contents(__DIR__.'/../../lang/hi.json'), true, flags: JSON_THROW_ON_ERROR);

        foreach ($hindi as $english => $translation) {
            preg_match_all('/:[a-z_]+/', $english, $expected);
            preg_match_all('/:[a-z_]+/', $translation, $actual);

            $this->assertEqualsCanonicalizing(array_unique($expected[0]), array_unique($actual[0]), "Placeholders differ for \"{$english}\"");
            $this->assertSame(substr_count($english, '|'), substr_count($translation, '|'), "Plural forms differ for \"{$english}\"");
        }
    }

    /**
     * @return array<string>
     */
    private function interfaceStrings(): array
    {
        $strings = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/../../app'));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            preg_match_all("/(?:__|trans_choice)\(\s*'((?:[^'\\\\]|\\\\.)*)'/", file_get_contents($file->getPathname()), $matches);

            foreach ($matches[1] as $string) {
                $strings[] = str_replace("\\'", "'", $string);
            }
        }

        return array_values(array_unique($strings));
    }
}
