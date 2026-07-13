<?php

namespace App\Support;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

/**
 * Bouwt een recovery-backup: een met wachtwoord (AES-256) versleutelde ZIP met
 * de volledige databasedump, de applicatiebroncode + webpagina's, de
 * configuratie (.env, incl. APP_KEY — nodig om versleutelde velden zoals
 * BSN/IBAN te kunnen herstellen) en de geüploade bestanden (documenten,
 * ondertekende PDF's). Alleen voor de Beheerder.
 *
 * Uitgesloten (niet nodig voor herstel of te herleiden): vendor/ (via
 * `composer install`), .git/, node_modules/ en de referentiemap IUASR/
 * (externe sites; het leidende design system staat al in public/assets).
 */
class Backup
{
    /** Mappen die niet in de backup horen (te herleiden of niet SIS-gerelateerd). */
    private const SKIP_MAPPEN = ['vendor', 'node_modules', '.git', '.idea', '.vscode', 'IUASR'];

    /**
     * Bouwt de versleutelde ZIP en geeft het pad naar het tijdelijke bestand terug.
     * De aanroeper is verantwoordelijk voor het opruimen (bv. deleteFileAfterSend).
     */
    public static function maak(string $wachtwoord): string
    {
        if (! class_exists(ZipArchive::class) || ! method_exists(ZipArchive::class, 'setEncryptionName')) {
            throw new RuntimeException('Versleutelde ZIP wordt niet ondersteund op deze server.');
        }

        $zipPad = tempnam(sys_get_temp_dir(), 'iuasr_backup_');
        $zip = new ZipArchive();
        if ($zip->open($zipPad, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Kon het backup-archief niet aanmaken.');
        }
        $zip->setPassword($wachtwoord);

        $tijdelijk = [];

        // 1. Databasedump (structuur + data).
        $sqlPad = tempnam(sys_get_temp_dir(), 'iuasr_sql_');
        $h = fopen($sqlPad, 'w');
        DatabaseDump::schrijf($h);
        fclose($h);
        self::voegBestand($zip, $sqlPad, 'database.sql');
        $tijdelijk[] = $sqlPad;

        // 2. Herstelinstructie / manifest.
        $zip->addFromString('LEESMIJ-herstel.txt', self::manifest());
        $zip->setEncryptionName('LEESMIJ-herstel.txt', ZipArchive::EM_AES_256);

        // 3. Applicatiebestanden (broncode, webpagina's, config incl. .env).
        self::voegMap($zip, base_path(), '', array_merge(self::SKIP_MAPPEN, ['storage']));

        // 4. Geüploade bestanden (private documenten + public disk).
        self::voegMap($zip, storage_path('app'), 'storage/app/', []);

        $zip->close();

        foreach ($tijdelijk as $t) {
            @unlink($t);
        }

        return $zipPad;
    }

    private static function voegBestand(ZipArchive $zip, string $absoluutPad, string $entry): void
    {
        $zip->addFile($absoluutPad, $entry);
        $zip->setEncryptionName($entry, ZipArchive::EM_AES_256);
    }

    /** Voegt een map recursief toe; mappen uit $skipMappen worden overgeslagen (niet doorlopen). */
    private static function voegMap(ZipArchive $zip, string $basis, string $prefix, array $skipMappen): void
    {
        if (! is_dir($basis)) {
            return;
        }

        $dirIterator = new RecursiveDirectoryIterator($basis, FilesystemIterator::SKIP_DOTS);
        $filter = new RecursiveCallbackFilterIterator($dirIterator, function ($current) use ($skipMappen) {
            if ($current->isDir() && in_array($current->getFilename(), $skipMappen, true)) {
                return false;
            }

            return true;
        });

        foreach (new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
            if (! $file->isFile() || ! $file->isReadable()) {
                continue;
            }
            $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($basis) + 1));
            $entry = $prefix.$rel;
            $zip->addFile($file->getPathname(), $entry);
            $zip->setEncryptionName($entry, ZipArchive::EM_AES_256);
        }
    }

    private static function manifest(): string
    {
        $datum = now()->toDateTimeString();
        $door = auth()->user()?->naam ?? 'onbekend';

        return <<<TXT
        IUASR Management Systeem — Recovery-backup
        ==========================================
        Gegenereerd : {$datum}
        Door        : {$door}

        INHOUD
        ------
        database.sql        Volledige databasedump (structuur + alle data).
        .env                Applicatieconfiguratie INCLUSIEF APP_KEY. De APP_KEY is
                            NOODZAKELIJK om versleutelde velden (BSN, rekeningnummer)
                            te kunnen ontsleutelen. Zonder deze sleutel zijn die
                            gegevens onherstelbaar.
        app/, config/, database/, public/, resources/, routes/, bootstrap/, ...
                            Applicatiebroncode en webpagina's (Blade-schermen).
        storage/app/...     Geüploade documenten en digitaal ondertekende PDF's.

        NIET INBEGREPEN (bewust)
        ------------------------
        vendor/             Herstel met 'composer install' (composer.lock zit erin).
        node_modules/, .git/, IUASR/ (externe-site/design-referentie).

        HERSTELPROCEDURE (globaal)
        --------------------------
        1. Pak dit archief uit met het gekozen wachtwoord.
        2. Plaats de bestanden in de webroot van de (interne) server.
        3. Voer 'composer install' uit om vendor/ te herstellen.
        4. Maak een lege database en importeer database.sql.
        5. Controleer .env: DB-gegevens en APP_URL; laat APP_KEY ONGEWIJZIGD.
        6. Leeg de cache: 'php artisan optimize:clear'.

        AVG / BEVEILIGING
        -----------------
        Dit archief bevat ALLE persoonsgegevens én de encryptiesleutel. Bewaar het
        uitsluitend versleuteld op een beveiligde, interne locatie en deel het niet.
        TXT;
    }
}
