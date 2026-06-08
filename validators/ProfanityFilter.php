<?php
/**
 * ProfanityFilter — Validasi konten dari kata kasar / tidak pantas
 * Mendukung Bahasa Indonesia dan ejaan variasi umum (leet speak, spasi, dsb.)
 */
class ProfanityFilter
{
    /**
     * Daftar kata kasar / tidak pantas dalam Bahasa Indonesia.
     * Kata disimpan dalam huruf kecil tanpa spasi untuk pencocokan fleksibel.
     */
    private static array $badWords = [
        // Umpatan umum
        'anjing', 'anj1ng', 'anying', 'ajg', 'ajing',
        'bangsat', 'b4ngsat', 'bngst',
        'babi', 'b4bi',
        'bajingan', 'bajingàn',
        'bedebah',
        'brengsek', 'br3ngs3k',
        'celeng',
        'kampret', 'k4mpr3t',
        'keparat', 'k3parat',
        'kurang ajar', 'kurangajar',
        'monyet', 'm0ny3t',
        'setan', 's3tan', 'syetan',
        'sialan', 's1alan',
        'tai', 't4i',
        'tolol', 't0lol',
        'goblok', 'g0bl0k', 'goblog',
        'idiot', '1diot',
        'dungu',
        'bego', 'b3go',
        'bodoh', 'b0doh',
        'geblek', 'g3bl3k',
        'gila', 'g1la',
        'edan',
        'oon', 'o0n',
        'pantek', 'pant3k',
        'puki', 'p*ki',
        'pepek', 'p3p3k',
        'kontol', 'k0ntol', 'k*ntol',
        'memek', 'm3m3k', 'm*m*k',
        'ngentot', 'ng3ntot', 'ng*ntot', 'entot',
        'jembut', 'j3mbut',
        'pelacur', 'p3lacur',
        'lonte', 'l0nte',
        'sundal',
        'jalang',
        'lacur',
        'bejat',
        'cabul',
        'bugil',
        'telanjang',  // hanya jika konteks tidak pantas
        'titit', 't1t1t',
        'tempik',
        'bokong',
        'bokep', 'b0k3p',
        'fuck', 'f*ck', 'f**k', 'fvck',
        'shit', 'sh1t', 'sh*t',
        'damn',
        'bastard',
        'asshole', 'a**hole',
        'bitch', 'b*tch',
        'cunt', 'c*nt',
        'dick', 'd*ck',
        'pussy',
        'nigger', 'n*gger',
        'whore',
    ];

    /**
     * Normalisasi teks: lowercase, hapus karakter aneh, ganti leet speak umum
     */
    private static function normalize(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');

        // Ganti leet speak umum
        $leet = [
            '@' => 'a',
            '4' => 'a',
            '3' => 'e',
            '1' => 'i',
            '0' => 'o',
            '5' => 's',
            '7' => 't',
            '$' => 's',
            '!' => 'i',
            '+' => 't',
        ];
        $text = strtr($text, $leet);

        // Hapus karakter non-alfanumerik kecuali spasi (untuk mendeteksi "b*bi" dll)
        $text = preg_replace('/[^a-z0-9\s]/u', '', $text);

        // Hapus spasi berlebih
        $text = preg_replace('/\s+/', ' ', trim($text));

        return $text;
    }

    /**
     * Cek apakah teks mengandung kata kasar.
     * Mengembalikan kata kasar yang ditemukan pertama kali, atau null jika bersih.
     */
    public static function detect(string $text): ?string
    {
        $normalized = self::normalize($text);

        // Juga cek versi tanpa spasi (untuk mendeteksi "k o n t o l" dll)
        $noSpace = str_replace(' ', '', $normalized);

        foreach (self::$badWords as $bad) {
            $normalBad = self::normalize($bad);

            // Cek di teks normal (dengan word boundary sederhana)
            if (preg_match('/(?<![a-z])' . preg_quote($normalBad, '/') . '(?![a-z])/u', $normalized)) {
                return $bad;
            }

            // Cek di versi tanpa spasi
            if (strlen($normalBad) >= 4 && str_contains($noSpace, $normalBad)) {
                return $bad;
            }
        }

        return null;
    }

    /**
     * Cek apakah teks bersih (tidak mengandung kata kasar).
     */
    public static function isClean(string $text): bool
    {
        return self::detect($text) === null;
    }

    /**
     * Validasi beberapa field sekaligus.
     * Mengembalikan pesan error atau null jika semua bersih.
     */
    public static function validate(array $fields): ?string
    {
        foreach ($fields as $label => $value) {
            $found = self::detect((string)$value);
            if ($found !== null) {
                return "Konten pada \"$label\" mengandung kata yang tidak pantas. Harap gunakan bahasa yang sopan.";
            }
        }
        return null;
    }
}
