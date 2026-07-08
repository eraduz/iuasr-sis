<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Maakt een volledige SQL-dump van de database met alleen PDO (geen externe
 * mysqldump-binary nodig, dus platform-onafhankelijk). Structuur (CREATE) +
 * data (INSERT) per tabel. Wordt gebruikt door de recovery-backup.
 */
class DatabaseDump
{
    /** Schrijft de volledige dump naar een geopende bestands-handle. */
    public static function schrijf($handle): void
    {
        $pdo = DB::getPdo();

        fwrite($handle, "-- IUASR SIS databasedump\n");
        fwrite($handle, '-- Gegenereerd: '.now()->toDateTimeString()."\n");
        fwrite($handle, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        foreach (self::tabellen() as $tabel) {
            $create = (array) DB::select('SHOW CREATE TABLE `'.$tabel.'`')[0];
            $createSql = array_values($create)[1];

            fwrite($handle, "-- --------------------------------------------------------\n");
            fwrite($handle, '-- Tabel: '.$tabel."\n\n");
            fwrite($handle, 'DROP TABLE IF EXISTS `'.$tabel."`;\n".$createSql.";\n\n");

            foreach (DB::table($tabel)->cursor() as $row) {
                $rij = (array) $row;
                $kolommen = '`'.implode('`,`', array_keys($rij)).'`';
                $waarden = implode(',', array_map(
                    fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v),
                    array_values($rij),
                ));
                fwrite($handle, 'INSERT INTO `'.$tabel.'` ('.$kolommen.') VALUES ('.$waarden.");\n");
            }

            fwrite($handle, "\n");
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
    }

    /** @return list<string> alle tabelnamen in de actieve database */
    private static function tabellen(): array
    {
        return array_map(
            fn ($r) => array_values((array) $r)[0],
            DB::select('SHOW TABLES'),
        );
    }
}
