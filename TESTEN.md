# Testen met behoud van data

Tijdens het testen met collega's mag de ingevoerde data **niet** verloren gaan
bij een code-update. Dat gaat goed zolang je de onderstaande regels volgt.

## De gouden regel

Na een code-update (`git pull`) draai je **alleen**:

```
php artisan sis:update
```

Dit maakt eerst een veiligheidssnapshot en draait daarna **alleen de nieuwe
migraties** — bestaande data blijft staan. (Of onder Windows: dubbelklik/voer
`update.ps1` uit; dat doet `git pull` + `sis:update`.)

## NOOIT tijdens het testen

Deze commando's **wissen alle data** (ze droppen alle tabellen en herseeden met
synthetische voorbeelddata). Gebruik ze niet op de test-database:

```
php artisan migrate:fresh      ❌ wist alles
php artisan migrate:refresh    ❌ wist alles
php artisan migrate:fresh --seed   ❌ wist alles
php artisan db:seed            ⚠️  voegt voorbeelddata toe (meestal niet nodig)
```

## Waarom je data behouden blijft

- **Aparte test-database.** De geautomatiseerde tests (`php artisan test`)
  draaien op `iuasr_sis_test`, nooit op de dev-database `iuasr_sis`. Testen raakt
  je testdata dus niet.
- **Incrementele migraties.** `php artisan migrate` (en dus `sis:update`) voert
  alleen migraties uit die nog niet gedraaid zijn; het verandert geen bestaande
  rijen en dropt geen tabellen.

## Vangnet: snapshot & herstel

Wil je vóór een risicovolle actie handmatig veiligstellen, of iets terugzetten:

```
php artisan sis:snapshot                 # maak nu een snapshot
php artisan sis:snapshot --naam=voor-demo # met een label
php artisan sis:restore                  # herstel de NIEUWSTE snapshot
php artisan sis:restore <bestand.sql>    # herstel een specifieke snapshot
```

Snapshots staan in `storage/app/db-snapshots/` (SQL-bestanden) en **buiten Git**
(bevatten testdata). Bewaar belangrijke snapshots eventueel apart.

## Nog even samengevat

| Situatie | Commando |
|---|---|
| Code bijgewerkt (na `git pull`) | `php artisan sis:update` |
| Snel veiligstellen | `php artisan sis:snapshot` |
| Per ongeluk data kwijt | `php artisan sis:restore` |
| Helemaal opnieuw met voorbeelddata (LET OP: wist alles) | `php artisan migrate:fresh --seed` |
