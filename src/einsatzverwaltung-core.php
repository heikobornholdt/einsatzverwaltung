<?php
namespace abrain\Einsatzverwaltung;

require_once dirname(__FILE__) . '/einsatzverwaltung-admin.php';
require_once dirname(__FILE__) . '/einsatzverwaltung-data.php';
require_once dirname(__FILE__) . '/einsatzverwaltung-utilities.php';
require_once dirname(__FILE__) . '/einsatzverwaltung-frontend.php';
require_once dirname(__FILE__) . '/Model/ReportAnnotation.php';
require_once dirname(__FILE__) . '/ReportAnnotationRepository.php';
require_once dirname(__FILE__) . '/Model/IncidentReport.php';
require_once dirname(__FILE__) . '/Util/Formatter.php';
require_once dirname(__FILE__) . '/Widgets/RecentIncidents.php';
require_once dirname(__FILE__) . '/Widgets/RecentIncidentsFormatted.php';
require_once dirname(__FILE__) . '/einsatzverwaltung-options.php';
require_once dirname(__FILE__) . '/einsatzverwaltung-shortcodes.php';
require_once dirname(__FILE__) . '/Import/Tool.php';
require_once dirname(__FILE__) . '/Export/Tool.php';
require_once dirname(__FILE__) . '/Frontend/ReportList.php';
require_once dirname(__FILE__) . '/Frontend/ReportListSettings.php';
require_once dirname(__FILE__) . '/ReportQuery.php';
require_once dirname(__FILE__) . '/TasksPage.php';

use abrain\Einsatzverwaltung\CustomFields\ColorPicker;
use abrain\Einsatzverwaltung\CustomFields\NumberInput;
use abrain\Einsatzverwaltung\CustomFields\PostSelector;
use abrain\Einsatzverwaltung\CustomFields\TextInput;
use abrain\Einsatzverwaltung\Import\Tool as ImportTool;
use abrain\Einsatzverwaltung\Export\Tool as ExportTool;
use abrain\Einsatzverwaltung\Model\ReportAnnotation;
use abrain\Einsatzverwaltung\Settings\MainPage;
use abrain\Einsatzverwaltung\Util\Formatter;
use abrain\Einsatzverwaltung\Widgets\RecentIncidents;
use abrain\Einsatzverwaltung\Widgets\RecentIncidentsFormatted;
use WP_User;

/**
 * Grundlegende Funktionen
 */
class Core
{
    const VERSION = '1.4.1';
    const DB_VERSION = 30;

   /**
    * Statische Variable, um die aktuelle (einzige!) Instanz dieser Klasse zu halten
    * @var Core
    */
    private static $instance = null;

    public $pluginFile;
    public $pluginBasename;
    public $pluginDir;
    public $pluginUrl;
    public $scriptUrl;
    public $styleUrl;

    private $argsEinsatz = array(
        'labels' => array(
            'name' => 'Einsatzberichte',
            'singular_name' => 'Einsatzbericht',
            'menu_name' => 'Einsatzberichte',
            'add_new' => 'Neu',
            'add_new_item' => 'Neuer Einsatzbericht',
            'edit' => 'Bearbeiten',
            'edit_item' => 'Einsatzbericht bearbeiten',
            'new_item' => 'Neuer Einsatzbericht',
            'view' => 'Ansehen',
            'view_item' => 'Einsatzbericht ansehen',
            'search_items' => 'Einsatzberichte suchen',
            'not_found' => 'Keine Einsatzberichte gefunden',
            'not_found_in_trash' => 'Keine Einsatzberichte im Papierkorb gefunden',
            'filter_items_list' => 'Liste der Einsatzberichte filtern',
            'items_list_navigation' => 'Navigation der Liste der Einsatzberichte',
            'items_list' => 'Liste der Einsatzberichte',
            'insert_into_item' => 'In den Einsatzbericht einf&uuml;gen',
            'uploaded_to_this_item' => 'Zu diesem Einsatzbericht hochgeladen',
            'view_items' => 'Einsatzberichte ansehen',
            //'attributes' => 'Attribute', // In WP 4.7 eingeführtes Label, für Einsatzberichte derzeit nicht relevant
        ),
        'public' => true,
        'has_archive' => true,
        'rewrite' => array(
            'feeds' => true
        ),
        'supports' => array('title', 'editor', 'thumbnail', 'publicize', 'author', 'revisions'),
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_nav_menus' => false,
        'show_in_admin_bar' => true,
        'capability_type' => array('einsatzbericht', 'einsatzberichte'),
        'map_meta_cap' => true,
        'capabilities' => array(
            'edit_post' => 'edit_einsatzbericht',
            'read_post' => 'read_einsatzbericht',
            'delete_post' => 'delete_einsatzbericht',
            'edit_posts' => 'edit_einsatzberichte',
            'edit_others_posts' => 'edit_others_einsatzberichte',
            'publish_posts' => 'publish_einsatzberichte',
            'read_private_posts' => 'read_private_einsatzberichte',
            'read' => 'read',
            'delete_posts' => 'delete_einsatzberichte',
            'delete_private_posts' => 'delete_private_einsatzberichte',
            'delete_published_posts' => 'delete_published_einsatzberichte',
            'delete_others_posts' => 'delete_others_einsatzberichte',
            'edit_private_posts' => 'edit_private_einsatzberichte',
            'edit_published_posts' => 'edit_published_einsatzberichte'
        ),
        'menu_position' => 5,
        'menu_icon' => 'dashicons-media-document',
        'taxonomies' => array('post_tag', 'category'),
        'delete_with_user' => false,
    );

    private $argsEinsatzart = array(
        'label' => 'Einsatzarten',
        'labels' => array(
            'name' => 'Einsatzarten',
            'singular_name' => 'Einsatzart',
            'menu_name' => 'Einsatzarten',
            'search_items' => 'Einsatzarten suchen',
            'popular_items' => 'H&auml;ufige Einsatzarten',
            'all_items' => 'Alle Einsatzarten',
            'parent_item' => '&Uuml;bergeordnete Einsatzart',
            'parent_item_colon' => '&Uuml;bergeordnete Einsatzart:',
            'edit_item' => 'Einsatzart bearbeiten',
            'view_item' => 'Einsatzart ansehen',
            'update_item' => 'Einsatzart aktualisieren',
            'add_new_item' => 'Neue Einsatzart',
            'new_item_name' => 'Einsatzart hinzuf&uuml;gen',
            'separate_items_with_commas' => 'Einsatzarten mit Kommas trennen',
            'add_or_remove_items' => 'Einsatzarten hinzuf&uuml;gen oder entfernen',
            'choose_from_most_used' => 'Aus h&auml;ufigen Einsatzarten w&auml;hlen',
            'not_found' => 'Keine Einsatzarten gefunden.',
            'no_terms' => 'Keine Einsatzarten',
            'items_list_navigation' => 'Navigation der Liste der Einsatzarten',
            'items_list' => 'Liste der Einsatzarten',
        ),
        'public' => true,
        'show_in_nav_menus' => false,
        'meta_box_cb' => 'abrain\Einsatzverwaltung\Admin::displayMetaBoxEinsatzart',
        'capabilities' => array(
            'manage_terms' => 'edit_einsatzberichte',
            'edit_terms' => 'edit_einsatzberichte',
            'delete_terms' => 'edit_einsatzberichte',
            'assign_terms' => 'edit_einsatzberichte'
        ),
        'hierarchical' => true
    );

    private $argsFahrzeug = array(
        'label' => 'Fahrzeuge',
        'labels' => array(
            'name' => 'Fahrzeuge',
            'singular_name' => 'Fahrzeug',
            'menu_name' => 'Fahrzeuge',
            'search_items' => 'Fahrzeuge suchen',
            'popular_items' => 'H&auml;ufig eingesetzte Fahrzeuge',
            'all_items' => 'Alle Fahrzeuge',
            'parent_item' => '&Uuml;bergeordnete Einheit',
            'parent_item_colon' => '&Uuml;bergeordnete Einheit:',
            'edit_item' => 'Fahrzeug bearbeiten',
            'view_item' => 'Fahrzeug ansehen',
            'update_item' => 'Fahrzeug aktualisieren',
            'add_new_item' => 'Neues Fahrzeug',
            'new_item_name' => 'Fahrzeug hinzuf&uuml;gen',
            'not_found' => 'Keine Fahrzeuge gefunden.',
            'no_terms' => 'Keine Fahrzeuge',
            'items_list_navigation' => 'Navigation der Fahrzeugliste',
            'items_list' => 'Fahrzeugliste',
        ),
        'public' => true,
        'show_in_nav_menus' => false,
        'hierarchical' => true,
        'capabilities' => array(
            'manage_terms' => 'edit_einsatzberichte',
            'edit_terms' => 'edit_einsatzberichte',
            'delete_terms' => 'edit_einsatzberichte',
            'assign_terms' => 'edit_einsatzberichte'
        )
    );

    private $argsExteinsatzmittel = array(
        'label' => 'Externe Einsatzmittel',
        'labels' => array(
            'name' => 'Externe Einsatzmittel',
            'singular_name' => 'Externes Einsatzmittel',
            'menu_name' => 'Externe Einsatzmittel',
            'search_items' => 'Externe Einsatzmittel suchen',
            'popular_items' => 'H&auml;ufig eingesetzte externe Einsatzmittel',
            'all_items' => 'Alle externen Einsatzmittel',
            'edit_item' => 'Externes Einsatzmittel bearbeiten',
            'view_item' => 'Externes Einsatzmittel ansehen',
            'update_item' => 'Externes Einsatzmittel aktualisieren',
            'add_new_item' => 'Neues externes Einsatzmittel',
            'new_item_name' => 'Externes Einsatzmittel hinzuf&uuml;gen',
            'separate_items_with_commas' => 'Externe Einsatzmittel mit Kommas trennen',
            'add_or_remove_items' => 'Externe Einsatzmittel hinzuf&uuml;gen oder entfernen',
            'choose_from_most_used' => 'Aus h&auml;ufig eingesetzten externen Einsatzmitteln w&auml;hlen',
            'not_found' => 'Keine externen Einsatzmittel gefunden.',
            'no_terms' => 'Keine externen Einsatzmittel',
            'items_list_navigation' => 'Navigation der Liste der externen Einsatzmittel',
            'items_list' => 'Liste der externen Einsatzmittel',
        ),
        'public' => true,
        'show_in_nav_menus' => false,
        'capabilities' => array(
            'manage_terms' => 'edit_einsatzberichte',
            'edit_terms' => 'edit_einsatzberichte',
            'delete_terms' => 'edit_einsatzberichte',
            'assign_terms' => 'edit_einsatzberichte'
        ),
        'rewrite' => array(
            'slug' => 'externe-einsatzmittel'
        )
    );

    private $argsAlarmierungsart = array(
        'label' => 'Alarmierungsart',
        'labels' => array(
            'name' => 'Alarmierungsarten',
            'singular_name' => 'Alarmierungsart',
            'menu_name' => 'Alarmierungsarten',
            'search_items' => 'Alarmierungsart suchen',
            'popular_items' => 'H&auml;ufige Alarmierungsarten',
            'all_items' => 'Alle Alarmierungsarten',
            'edit_item' => 'Alarmierungsart bearbeiten',
            'view_item' => 'Alarmierungsart ansehen',
            'update_item' => 'Alarmierungsart aktualisieren',
            'add_new_item' => 'Neue Alarmierungsart',
            'new_item_name' => 'Alarmierungsart hinzuf&uuml;gen',
            'separate_items_with_commas' => 'Alarmierungsarten mit Kommas trennen',
            'add_or_remove_items' => 'Alarmierungsarten hinzuf&uuml;gen oder entfernen',
            'choose_from_most_used' => 'Aus h&auml;ufigen Alarmierungsarten w&auml;hlen',
            'not_found' => 'Keine Alarmierungsarten gefunden.',
            'no_terms' => 'Keine Alarmierungsarten',
            'items_list_navigation' => 'Navigation der Liste der Alarmierungsarten',
            'items_list' => 'Liste der Alarmierungsarten',
        ),
        'public' => true,
        'show_in_nav_menus' => false,
        'capabilities' => array(
            'manage_terms' => 'edit_einsatzberichte',
            'edit_terms' => 'edit_einsatzberichte',
            'delete_terms' => 'edit_einsatzberichte',
            'assign_terms' => 'edit_einsatzberichte'
        )
    );

    /**
     * @var Admin
     */
    private $admin;

    /**
     * @var Data
     */
    private $data;

    /**
     * @var Frontend
     */
    private $frontend;
    
    /**
     * @var Options
     */
    public $options;
    
    /**
     * @var ImportTool
     */
    private $importTool;
    
    /**
     * @var ExportTool
     */
    private $exportTool;
    
    /**
     * @var TasksPage
     */
    private $tasksPage;
    
    /**
     * @var Utilities
     */
    public $utilities;

    /**
     * @var Formatter
     */
    public $formatter;

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->pluginFile = einsatzverwaltung_plugin_file();
        $this->pluginBasename = plugin_basename($this->pluginFile);
        $this->pluginDir = plugin_dir_path($this->pluginFile);
        $this->pluginUrl = plugin_dir_url($this->pluginFile);
        $this->scriptUrl = $this->pluginUrl . 'js/';
        $this->styleUrl = $this->pluginUrl . 'css/';

        $this->utilities = new Utilities($this);
        $this->options = new Options($this->utilities);
        $this->utilities->setDependencies($this->options); // FIXME Yay, zirkuläre Abhängigkeiten!

        $this->formatter = new Formatter($this->options, $this->utilities); // TODO In Singleton umwandeln

        $this->admin = new Admin($this, $this->options, $this->utilities);
        $this->data = new Data($this, $this->utilities, $this->options);
        $this->frontend = new Frontend($this, $this->options, $this->utilities, $this->formatter);
        $this->registerSettings();
        $this->shortcodes = new Shortcodes($this->utilities, $this, $this->options, $this->formatter);

        // Tools
        $this->importTool = new ImportTool($this->utilities, $this->options, $this->data);
        $this->exportTool = new ExportTool();
        $this->tasksPage = new TasksPage($this->utilities, $this->data);

        $this->addHooks();
    }

    private function addHooks()
    {
        add_action('init', array($this, 'onInit'));
        add_action('plugins_loaded', array($this, 'onPluginsLoaded'));
        register_activation_hook($this->pluginFile, array($this, 'onActivation'));
        register_deactivation_hook($this->pluginFile, array($this, 'onDeactivation'));
        add_action('widgets_init', array($this, 'registerWidgets'));
        add_filter('user_has_cap', array($this, 'userHasCap'), 10, 4);
        add_action('parse_query', array($this, 'einsatznummerMetaQuery'));
    }

    /**
     * Wird beim Aktivieren des Plugins aufgerufen
     */
    public function onActivation()
    {
        add_option('einsatzvw_db_version', self::DB_VERSION);

        $this->maybeUpdate();
        update_option('einsatzvw_version', self::VERSION);

        // Posttypen registrieren
        $this->registerTypes();
        $this->addRewriteRules();

        // Permalinks aktualisieren
        flush_rewrite_rules();
    }

    /**
     * Wird beim Deaktivieren des Plugins aufgerufen
     */
    public function onDeactivation()
    {
        // Permalinks aktualisieren (derzeit ohne Effekt, siehe https://core.trac.wordpress.org/ticket/29118)
        flush_rewrite_rules();
    }

    /**
     * Plugin initialisieren
     */
    public function onInit()
    {
        $this->registerTypes();
        $this->addRewriteRules();
        if ($this->options->isFlushRewriteRules()) {
            flush_rewrite_rules();
            $this->options->setFlushRewriteRules(false);
        }
    }

    public function onPluginsLoaded()
    {
        $this->maybeUpdate();
        update_option('einsatzvw_version', self::VERSION);
    }

    /**
     * Erzeugt den neuen Beitragstyp Einsatzbericht und die zugehörigen Taxonomien
     */
    private function registerTypes()
    {
        // Anpassungen der Parameter
        $this->argsEinsatz['rewrite']['slug'] = $this->options->getRewriteSlug();

        register_post_type('einsatz', $this->argsEinsatz);
        register_taxonomy('einsatzart', 'einsatz', $this->argsEinsatzart);
        register_taxonomy('fahrzeug', 'einsatz', $this->argsFahrzeug);
        register_taxonomy('exteinsatzmittel', 'einsatz', $this->argsExteinsatzmittel);
        register_taxonomy('alarmierungsart', 'einsatz', $this->argsAlarmierungsart);

        register_meta('post', 'einsatz_einsatzende', array(
            'type' => 'string',
            'description' => 'Datum und Uhrzeit, zu der der Einsatz endete.',
            'single' => true,
            'sanitize_callback' => array($this->data, 'sanitizeTimeOfEnding'),
            'show_in_rest' => false
        ));

        register_meta('post', 'einsatz_einsatzleiter', array(
            'type' => 'string',
            'description' => 'Name der Person, die die Einsatzleitung innehatte.',
            'single' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest' => false
        ));

        register_meta('post', 'einsatz_einsatzort', array(
            'type' => 'string',
            'description' => 'Die Örtlichkeit, an der der Einsatz stattgefunden hat.',
            'single' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest' => false
        ));

        register_meta('post', 'einsatz_fehlalarm', array(
            'type' => 'boolean',
            'description' => 'Vermerk, ob es sich um einen Fehlalarm handelte.',
            'single' => true,
            'sanitize_callback' => array('Utilities', 'sanitizeCheckbox'),
            'show_in_rest' => false
        ));

        register_meta('post', 'einsatz_hasimages', array(
            'type' => 'boolean',
            'description' => 'Vermerk, ob der Einsatzbericht Bilder enthält.',
            'single' => true,
            'sanitize_callback' => array('Utilities', 'sanitizeCheckbox'),
            'show_in_rest' => false
        ));

        register_meta('post', 'einsatz_incidentNumber', array(
            'type' => 'string',
            'description' => 'Einsatznummer.',
            'single' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest' => false
        ));

        register_meta('post', 'einsatz_mannschaft', array(
            'type' => 'string',
            'description' => 'Angaben über die Personalstärke für diesen Einsatz.',
            'single' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest' => false
        ));

        register_meta('post', 'einsatz_special', array(
            'type' => 'boolean',
            'description' => 'Vermerk, ob es sich um einen besonderen Einsatzbericht handelt.',
            'single' => true,
            'sanitize_callback' => array('Utilities', 'sanitizeCheckbox'),
            'show_in_rest' => false
        ));

        // Vermerke registrieren
        $annotationRepository = ReportAnnotationRepository::getInstance();
        $annotationRepository->addAnnotation(new ReportAnnotation(
            'images',
            'Bilder im Bericht',
            'einsatz_hasimages',
            'camera',
            'Einsatzbericht enthält Bilder',
            'Einsatzbericht enthält keine Bilder'
        ));
        $annotationRepository->addAnnotation(new ReportAnnotation(
            'special',
            'Besonderer Einsatz',
            'einsatz_special',
            'star',
            'Besonderer Einsatz',
            'Kein besonderer Einsatz'
        ));
        $annotationRepository->addAnnotation(new ReportAnnotation(
            'falseAlarm',
            'Fehlalarm',
            'einsatz_fehlalarm',
            '',
            'Fehlalarm',
            'Kein Fehlalarm'
        ));

        require dirname(__FILE__) . '/TaxonomyCustomFields.php';
        require dirname(__FILE__) . '/CustomFields/CustomField.php';
        require dirname(__FILE__) . '/CustomFields/ColorPicker.php';
        require dirname(__FILE__) . '/CustomFields/NumberInput.php';
        require dirname(__FILE__) . '/CustomFields/PostSelector.php';
        require dirname(__FILE__) . '/CustomFields/TextInput.php';

        $taxonomyCustomFields = new TaxonomyCustomFields();
        $taxonomyCustomFields->addTextInput('exteinsatzmittel', new TextInput(
            'url',
            'URL',
            'URL zu mehr Informationen &uuml;ber ein externes Einsatzmittel, beispielsweise dessen Webseite.'
        ));
        $taxonomyCustomFields->addColorpicker('einsatzart', new ColorPicker(
            'typecolor',
            'Farbe',
            'Ordne dieser Einsatzart eine Farbe zu. Einsatzarten ohne Farbe erben diese gegebenenfalls von übergeordneten Einsatzarten.'
        ));
        $taxonomyCustomFields->addPostSelector('fahrzeug', new PostSelector(
            'fahrzeugpid',
            'Fahrzeugseite',
            'Seite mit mehr Informationen &uuml;ber das Fahrzeug. Wird in Einsatzberichten mit diesem Fahrzeug verlinkt.',
            array('einsatz', 'attachment', 'ai1ec_event', 'tribe_events')
        ));
        $taxonomyCustomFields->addNumberInput('fahrzeug', new NumberInput(
            'vehicleorder',
            'Reihenfolge',
            'Optionale Angabe, mit der die Anzeigereihenfolge der Fahrzeuge beeinflusst werden kann. Fahrzeuge mit der kleineren Zahl werden zuerst angezeigt, anschlie&szlig;end diejenigen ohne Angabe bzw. dem Wert 0 in alphabetischer Reihenfolge.'
        ));
    }

    private function registerSettings()
    {
        require_once dirname(__FILE__) . '/Settings/MainPage.php';
        $mainPage = new MainPage($this->options);
        add_action('admin_menu', array($mainPage, 'addToSettingsMenu'));
        add_action('admin_init', array($mainPage, 'registerSettings'));
    }

    private function addRewriteRules()
    {
        global $wp_rewrite;
        if ($wp_rewrite->using_permalinks()) {
            $base = ltrim($wp_rewrite->front, '/') . $this->options->getRewriteSlug();
            add_rewrite_rule(
                $base . '/(\d{4})/page/(\d{1,})/?$',
                'index.php?post_type=einsatz&year=$matches[1]&paged=$matches[2]',
                'top'
            );
            add_rewrite_rule($base . '/(\d{4})/?$', 'index.php?post_type=einsatz&year=$matches[1]', 'top');
        }

        add_rewrite_tag('%einsatznummer%', '([^&]+)');
    }

    /**
     * @param \WP_Query $query
     */
    public function einsatznummerMetaQuery($query)
    {
        $enr = $query->get('einsatznummer');
        if (!empty($enr)) {
            $query->set('post_type', 'einsatz');
            $query->set('meta_key', 'einsatz_incidentNumber');
            $query->set('meta_value', $enr);
        }
    }

    public function registerWidgets()
    {
        register_widget(new RecentIncidents($this->options, $this->utilities, $this->formatter));
        register_widget(new RecentIncidentsFormatted($this->formatter, $this->utilities));
    }

    /**
     * Berechnet die nächste freie Einsatznummer für das gegebene Jahr
     *
     * @param string $jahr
     * @param bool $minuseins Wird beim Speichern der zusätzlichen Einsatzdaten in einsatzverwaltung_save_postdata
     * benötigt, da der Einsatzbericht bereits gespeichert wurde, aber bei der Zählung für die Einsatznummer
     * ausgelassen werden soll
     *
     * @return string Nächste freie Einsatznummer im angegebenen Jahr
     */
    public function getNextEinsatznummer($jahr, $minuseins = false)
    {
        if (empty($jahr) || !is_numeric($jahr)) {
            $jahr = date('Y');
        }

        return $this->formatEinsatznummer($jahr, $this->data->getNumberOfIncidentReports($jahr) + ($minuseins ? 0 : 1));
    }

    /**
     * Formatiert die Einsatznummer
     *
     * @param string $jahr Jahreszahl
     * @param int $nummer Laufende Nummer des Einsatzes im angegebenen Jahr
     *
     * @return string Formatierte Einsatznummer
     */
    public function formatEinsatznummer($jahr, $nummer)
    {
        $stellen = $this->options->getEinsatznummerStellen();
        $lfdvorne = $this->options->isEinsatznummerLfdVorne();
        if ($lfdvorne) {
            return str_pad($nummer, $stellen, "0", STR_PAD_LEFT).$jahr;
        } else {
            return $jahr.str_pad($nummer, $stellen, "0", STR_PAD_LEFT);
        }
    }

    /**
     * Gibt den Link zu einem bestimmten Jahresarchiv zurück, berücksichtigt dabei die Permalink-Einstellungen
     *
     * @param string $year
     *
     * @return string
     */
    public function getYearArchiveLink($year)
    {
        global $wp_rewrite;
        $link = get_post_type_archive_link('einsatz');
        $link = ($wp_rewrite->using_permalinks() ? trailingslashit($link) : $link . '&year=') . $year;
        return user_trailingslashit($link);
    }

    /**
     * Gibt die möglichen Berechtigungen für Einsatzberichte zurück
     *
     * @return array
     */
    public function getCapabilities()
    {
        return array(
            'edit_einsatzberichte',
            'edit_private_einsatzberichte',
            'edit_published_einsatzberichte',
            'edit_others_einsatzberichte',
            'publish_einsatzberichte',
            'read_private_einsatzberichte',
            'delete_einsatzberichte',
            'delete_private_einsatzberichte',
            'delete_published_einsatzberichte',
            'delete_others_einsatzberichte'
        );
    }

    /**
     * Prüft und vergibt Benutzerrechte zur Laufzeit
     *
     * @param array $allcaps Effektive Nutzerrechte
     * @param array $caps Die angefragten Nutzerrechte
     * @param array $args Zusätzliche Parameter wie Objekt-ID
     * @param WP_User $user Benutzerobjekt
     *
     * @return array Die gefilterten oder erweiterten Nutzerrechte
     */
    public function userHasCap($allcaps, $caps, $args, $user)
    {
        $requestedCaps = array_intersect($this->getCapabilities(), $caps);

        // Wenn es nicht um Berechtigungen aus der Einsatzverwaltung geht, können wir uns den Rest sparen
        if (count($requestedCaps) == 0) {
            return $allcaps;
        }

        // Wenn der Benutzer mindestens einer berechtigten Rolle zugeordnet ist, werden die Berechtigungen erteilt
        $allowedUserRoles = array_filter($user->roles, array($this->options, 'isRoleAllowedToEdit'));
        if (count($allowedUserRoles) > 0) {
            foreach ($requestedCaps as $requestedCap) {
                $allcaps[$requestedCap] = 1;
            }
        }

        return $allcaps;
    }

    private function maybeUpdate()
    {
        $currentDbVersion = get_option('einsatzvw_db_version');
        if (!empty($currentDbVersion) && $currentDbVersion >= self::DB_VERSION) {
            return;
        }

        $update = $this->getUpdater();
        $updateResult = $update->doUpdate($currentDbVersion, self::DB_VERSION);
        if (is_wp_error($updateResult)) {
            error_log("Das Datenbank-Upgrade wurde mit folgendem Fehler beendet: {$updateResult->get_error_message()}");
        }
    }

    /**
     * @return Update
     */
    public function getUpdater()
    {
        require_once(__DIR__ . '/einsatzverwaltung-update.php');
        return new Update($this, $this->options, $this->utilities, $this->data);
    }

    /**
     * @return Admin
     */
    public function getAdmin()
    {
        return $this->admin;
    }

    /**
     * @return Data
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @return Frontend
     */
    public function getFrontend()
    {
        return $this->frontend;
    }

    /**
     * @return Shortcodes
     */
    public function getShortcodes()
    {
        return $this->shortcodes;
    }

    /**
     * @return ImportTool
     */
    public function getImportTool()
    {
        return $this->importTool;
    }

    /**
     * @return ExportTool
     */
    public function getExportTool()
    {
        return $this->exportTool;
    }

    /**
     * @return TasksPage
     */
    public function getTasksPage()
    {
        return $this->tasksPage;
    }

    /**
     * Falls die einzige Instanz noch nicht existiert, erstelle sie
     * Gebe die einzige Instanz dann zurück
     *
     * @return   Core
     */
    public static function getInstance()
    {
        if (null === self::$instance) {
            self::$instance = new Core();
        }
        return self::$instance;
    }
}

Core::getInstance();
