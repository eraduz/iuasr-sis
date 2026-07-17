<?php

namespace Database\Seeders;

use App\Enums\Quotesoort;
use App\Models\Quote;
use Illuminate\Database\Seeder;

/**
 * De 99 Schone Namen van Allah (Asma ul-Husna) voor de zijbalk, in de gangbare
 * volgorde: transliteratie, Arabisch schrift en de Nederlandse betekenis.
 *
 * IDEMPOTENT op (soort, titel): opnieuw draaien voegt niets dubbel toe en laat
 * een door de Beheerder aangepaste betekenis of een geüploade afbeelding met
 * rust. Zo kan deze seeder veilig meelopen in DatabaseSeeder.
 *
 * De afbeeldingen worden NIET meegeleverd — die maakt de opdrachtgever zelf en
 * koppelt hij via Beheer → Quotes. Zonder afbeelding toont de zijbalk gewoon de
 * Arabische tekst; de quote werkt dus meteen.
 */
class QuoteSeeder extends Seeder
{
    /** @var array<int, array{0:string,1:string,2:string}> transliteratie, Arabisch, betekenis */
    private const NAMEN = [
        ['Ar-Rahman', 'الرحمن', 'De Meest Barmhartige'],
        ['Ar-Rahiem', 'الرحيم', 'De Meest Genadevolle'],
        ['Al-Malik', 'الملك', 'De Koning, de Heerser'],
        ['Al-Quddoes', 'القدوس', 'De Allerheiligste'],
        ['As-Salaam', 'السلام', 'De Bron van Vrede'],
        ['Al-Mu’min', 'المؤمن', 'De Schenker van Veiligheid'],
        ['Al-Muhaymin', 'المهيمن', 'De Beschermer, de Waker'],
        ['Al-Aziez', 'العزيز', 'De Almachtige'],
        ['Al-Djabbaar', 'الجبار', 'De Onweerstaanbare'],
        ['Al-Mutakabbir', 'المتكبر', 'De Majestueuze'],
        ['Al-Chaaliq', 'الخالق', 'De Schepper'],
        ['Al-Baari’', 'البارئ', 'De Voortbrenger'],
        ['Al-Musawwir', 'المصور', 'De Vormgever'],
        ['Al-Ghaffaar', 'الغفار', 'De Altijd Vergevende'],
        ['Al-Qahhaar', 'القهار', 'De Overheerser'],
        ['Al-Wahhaab', 'الوهاب', 'De Gulle Schenker'],
        ['Ar-Razzaaq', 'الرزاق', 'De Voorziener'],
        ['Al-Fattaah', 'الفتاح', 'De Opener, de Verlosser'],
        ['Al-Aliem', 'العليم', 'De Alwetende'],
        ['Al-Qaabid', 'القابض', 'De Inhouder'],
        ['Al-Baasit', 'الباسط', 'De Uitbreider'],
        ['Al-Chaafid', 'الخافض', 'De Vernederaar'],
        ['Ar-Raafi’', 'الرافع', 'De Verheffer'],
        ['Al-Mu’izz', 'المعز', 'De Eer Schenker'],
        ['Al-Muzill', 'المذل', 'De Onteraar'],
        ['As-Samie’', 'السميع', 'De Alhorende'],
        ['Al-Basier', 'البصير', 'De Alziende'],
        ['Al-Hakam', 'الحكم', 'De Rechter'],
        ['Al-Adl', 'العدل', 'De Rechtvaardige'],
        ['Al-Latief', 'اللطيف', 'De Zachtmoedige, de Subtiele'],
        ['Al-Chabier', 'الخبير', 'De Albewuste'],
        ['Al-Haliem', 'الحليم', 'De Verdraagzame'],
        ['Al-Aziem', 'العظيم', 'De Geweldige'],
        ['Al-Ghafoer', 'الغفور', 'De Vergevensgezinde'],
        ['Ash-Shakoer', 'الشكور', 'De Waarderende'],
        ['Al-Alie', 'العلي', 'De Allerhoogste'],
        ['Al-Kabier', 'الكبير', 'De Grootste'],
        ['Al-Hafiez', 'الحفيظ', 'De Bewaarder'],
        ['Al-Muqiet', 'المقيت', 'De Onderhouder'],
        ['Al-Hasieb', 'الحسيب', 'De Afrekenaar'],
        ['Al-Djaliel', 'الجليل', 'De Verhevene'],
        ['Al-Kariem', 'الكريم', 'De Edelmoedige'],
        ['Ar-Raqieb', 'الرقيب', 'De Waakzame'],
        ['Al-Mudjieb', 'المجيب', 'De Verhoorder van gebeden'],
        ['Al-Waasi’', 'الواسع', 'De Alomvattende'],
        ['Al-Hakiem', 'الحكيم', 'De Alwijze'],
        ['Al-Wadoed', 'الودود', 'De Liefdevolle'],
        ['Al-Madjied', 'المجيد', 'De Glorierijke'],
        ['Al-Baa’ith', 'الباعث', 'De Opwekker'],
        ['Ash-Shahied', 'الشهيد', 'De Getuige'],
        ['Al-Haqq', 'الحق', 'De Waarheid'],
        ['Al-Wakiel', 'الوكيل', 'De Vertrouwenspersoon'],
        ['Al-Qawie', 'القوي', 'De Sterke'],
        ['Al-Matien', 'المتين', 'De Standvastige'],
        ['Al-Walie', 'الولي', 'De Beschermende Vriend'],
        ['Al-Hamied', 'الحميد', 'De Geprezene'],
        ['Al-Muhsie', 'المحصي', 'De Alberekenende'],
        ['Al-Mubdi’', 'المبدئ', 'De Beginner'],
        ['Al-Mu’ied', 'المعيد', 'De Hersteller'],
        ['Al-Muhyie', 'المحيي', 'De Levenschenker'],
        ['Al-Mumiet', 'المميت', 'De Doodbrenger'],
        ['Al-Hayy', 'الحي', 'De Eeuwig Levende'],
        ['Al-Qayyoem', 'القيوم', 'De Zelfbestaande'],
        ['Al-Waadjid', 'الواجد', 'De Vinder'],
        ['Al-Maadjid', 'الماجد', 'De Nobele'],
        ['Al-Waahid', 'الواحد', 'De Enige'],
        ['Al-Ahad', 'الأحد', 'De Ene'],
        ['As-Samad', 'الصمد', 'De Onafhankelijke, tot Wie men zich wendt'],
        ['Al-Qaadir', 'القادر', 'De Machtige'],
        ['Al-Muqtadir', 'المقتدر', 'De Bepaler'],
        ['Al-Muqaddim', 'المقدم', 'De Bevorderaar'],
        ['Al-Mu’achchir', 'المؤخر', 'De Uitsteller'],
        ['Al-Awwal', 'الأول', 'De Eerste'],
        ['Al-Aachir', 'الآخر', 'De Laatste'],
        ['Az-Zaahir', 'الظاهر', 'De Zichtbare'],
        ['Al-Baatin', 'الباطن', 'De Verborgene'],
        ['Al-Waalie', 'الوالي', 'De Bestuurder'],
        ['Al-Muta’aalie', 'المتعالي', 'De Meest Verhevene'],
        ['Al-Barr', 'البر', 'De Weldoener'],
        ['At-Tawwaab', 'التواب', 'De Berouwaanvaardende'],
        ['Al-Muntaqim', 'المنتقم', 'De Vergelder'],
        ['Al-Afoew', 'العفو', 'De Kwijtschelder'],
        ['Ar-Ra’oef', 'الرؤوف', 'De Meest Vriendelijke'],
        ['Maalik-ul-Mulk', 'مالك الملك', 'De Eigenaar van alle Heerschappij'],
        ['Zul-Djalaali wal-Ikraam', 'ذو الجلال والإكرام', 'De Heer van Majesteit en Vrijgevigheid'],
        ['Al-Muqsit', 'المقسط', 'De Billijke'],
        ['Al-Djaami’', 'الجامع', 'De Verzamelaar'],
        ['Al-Ghanie', 'الغني', 'De Zelfgenoegzame'],
        ['Al-Mughnie', 'المغني', 'De Verrijker'],
        ['Al-Maani’', 'المانع', 'De Weerhouder'],
        ['Ad-Daarr', 'الضار', 'De Beproever'],
        ['An-Naafi’', 'النافع', 'De Begunstiger'],
        ['An-Noer', 'النور', 'Het Licht'],
        ['Al-Haadie', 'الهادي', 'De Gids'],
        ['Al-Badie’', 'البديع', 'De Onvergelijkelijke Schepper'],
        ['Al-Baaqie', 'الباقي', 'De Eeuwigblijvende'],
        ['Al-Waarith', 'الوارث', 'De Erfgenaam'],
        ['Ar-Rashied', 'الرشيد', 'De Rechtgeleide'],
        ['As-Saboer', 'الصبور', 'De Geduldige'],
    ];

    public function run(): void
    {
        foreach (self::NAMEN as $i => [$titel, $arabisch, $betekenis]) {
            Quote::firstOrCreate(
                ['soort' => Quotesoort::SchoneNaam, 'titel' => $titel],
                [
                    'arabisch' => $arabisch,
                    'betekenis' => $betekenis,
                    'volgorde' => $i + 1,
                    'actief' => true,
                ]
            );
        }
    }
}
