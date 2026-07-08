<?php
/**
 * IUASR — Documentverificatie shortcode voor WordPress.
 *
 * Plaats deze code in het thema-bestand functions.php (of in een kleine
 * site-specifieke plugin). Gebruik daarna de shortcode [iuasr_verificatie]
 * op een pagina van de publieke website.
 *
 * De shortcode toont een invulveld voor de verificatiecode en stuurt de
 * bezoeker door naar de openbare verificatiepagina van het SIS. Stel hieronder
 * de basis-URL in van het SIS-verificatie-endpoint (moet publiek bereikbaar
 * zijn; overleg met beheer over de netwerk-/reverse-proxy-configuratie).
 */

if (! defined('ABSPATH')) {
    exit; // directe toegang blokkeren
}

// Pas dit aan naar de publieke URL van het verificatie-endpoint.
if (! defined('IUASR_VERIFICATIE_URL')) {
    define('IUASR_VERIFICATIE_URL', 'https://sis.iuasr.nl/verificatie');
}

function iuasr_verificatie_shortcode($atts = array())
{
    $action = esc_url(IUASR_VERIFICATIE_URL);

    ob_start(); ?>
    <form method="get" action="<?php echo $action; ?>" style="max-width:420px;display:flex;gap:8px;flex-wrap:wrap;">
        <input type="text" name="code" required placeholder="IUASR-XXXX-XXXX"
               style="flex:1;min-width:200px;padding:10px 12px;border:1px solid #cfcfd6;border-radius:8px;">
        <button type="submit"
                style="background:#1E1446;color:#fff;border:0;border-radius:8px;padding:10px 18px;font-weight:600;cursor:pointer;">
            Document controleren
        </button>
    </form>
    <p style="color:#666;font-size:13px;margin-top:8px;">
        Voer de verificatiecode in die onderaan het IUASR-document staat.
    </p>
    <?php
    return ob_get_clean();
}

add_shortcode('iuasr_verificatie', 'iuasr_verificatie_shortcode');
