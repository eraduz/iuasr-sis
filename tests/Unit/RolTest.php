<?php

namespace Tests\Unit;

use App\Enums\Rol;
use PHPUnit\Framework\TestCase;

/**
 * Borgt de niet-onderhandelbare rolscheiding op enum-niveau. Deze regels zijn
 * de kern van het ontwerp (PvA §5) en mogen niet stilzwijgend veranderen.
 */
class RolTest extends TestCase
{
    public function test_studentenzaken_ziet_of_muteert_geen_cijfers(): void
    {
        $this->assertFalse(Rol::Studentenzaken->magCijfersInzien());
        $this->assertFalse(Rol::Studentenzaken->magCijfersInvoeren());
    }

    public function test_docent_voert_cijfers_in_en_ziet_ze(): void
    {
        $this->assertTrue(Rol::Docent->magCijfersInvoeren());
        $this->assertTrue(Rol::Docent->magCijfersInzien());
    }

    public function test_examencommissie_en_directie_zien_cijfers(): void
    {
        $this->assertTrue(Rol::Examencommissie->magCijfersInzien());
        $this->assertTrue(Rol::Directie->magCijfersInzien());
        // Directie heeft leesrecht, geen invoer.
        $this->assertFalse(Rol::Directie->magCijfersInvoeren());
    }

    public function test_alleen_studentenzaken_en_beheerder_beheren_inschrijving(): void
    {
        $this->assertTrue(Rol::Studentenzaken->magInschrijvingBeheren());
        $this->assertTrue(Rol::Beheerder->magInschrijvingBeheren());
        $this->assertFalse(Rol::Docent->magInschrijvingBeheren());
        $this->assertFalse(Rol::Examencommissie->magInschrijvingBeheren());
    }
}
