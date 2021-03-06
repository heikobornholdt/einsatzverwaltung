<?php

namespace abrain\Einsatzverwaltung\Settings\Pages;

use abrain\Einsatzverwaltung\Frontend\AnnotationIconBar;
use abrain\Einsatzverwaltung\Model\IncidentReport;
use abrain\Einsatzverwaltung\Utilities;

/**
 * General settings page
 *
 * @package abrain\Einsatzverwaltung\Settings\Pages
 */
class General extends SubPage
{
    public function __construct()
    {
        parent::__construct('general', 'Allgemein');

        add_filter('pre_update_option_einsatzvw_rewrite_slug', array($this, 'maybeRewriteSlugChanged'), 10, 2);
        add_filter('pre_update_option_einsatzvw_category', array($this, 'maybeCategoryChanged'), 10, 2);
        add_filter('pre_update_option_einsatzvw_loop_only_special', array($this, 'maybeCategorySpecialChanged'), 10, 2);
    }

    public function addSettingsFields()
    {
        add_settings_field(
            'einsatzvw_permalinks',
            'Permalinks',
            array($this, 'echoFieldPermalinks'),
            $this->settingsApiPage,
            'einsatzvw_settings_general'
        );
        add_settings_field(
            'einsatzvw_einsatznummer_mainloop',
            'Einsatzbericht als Beitrag',
            array($this, 'echoFieldMainloop'),
            $this->settingsApiPage,
            'einsatzvw_settings_general'
        );
        add_settings_field(
            'einsatzvw_settings_listannotations',
            'Vermerke',
            array($this, 'echoFieldAnnotations'),
            $this->settingsApiPage,
            'einsatzvw_settings_general'
        );
    }

    public function addSettingsSections()
    {
        add_settings_section(
            'einsatzvw_settings_general',
            '',
            null,
            $this->settingsApiPage
        );
    }

    /**
     * Gibt die Einstellmöglichkeit aus, ob und wie Einsatzberichte zusammen mit anderen Beiträgen ausgegeben werden
     * sollen
     */
    public function echoFieldMainloop()
    {
        echo '<fieldset>';
        $this->echoSettingsCheckbox(
            'einsatzvw_show_einsatzberichte_mainloop',
            'Einsatzberichte zwischen den regul&auml;ren WordPress-Beitr&auml;gen anzeigen'
        );
        echo '<p class="description">L&auml;sst die Einsatzberichte z.B. auf der Startseite, im Widget &quot;Letzte Beitr&auml;ge&quot; oder auch im Beitragsfeed erscheinen</p>';

        echo '<p><label for="einsatzvw_category">';
        echo 'Davon unabh&auml;ngig Einsatzberichte immer in folgender Kategorie anzeigen:';
        echo '&nbsp;</label>';
        $categoryId = intval(get_option('einsatzvw_category', -1));
        wp_dropdown_categories(array(
            'show_option_none' => '- keine -',
            'hide_empty' => false,
            'name' => 'einsatzvw_category',
            'selected' => $categoryId,
            'orderby' => 'name',
            'hierarchical' => true
        ));
        echo '</p>';


        $this->echoSettingsCheckbox(
            'einsatzvw_loop_only_special',
            'Nur als besonders markierte Einsatzberichte zwischen den regul&auml;ren WordPress-Beitr&auml;gen bzw. in der Kategorie anzeigen.'
        );
        echo '<p class="description">Mit dieser Einstellung gelten die beiden oberen Einstellungen nur f&uuml;r als besonders markierte Einsatzberichte.</p>';
        echo '</fieldset>';
    }

    /**
     * @since 1.0.0
     */
    public function echoFieldPermalinks()
    {
        echo '<fieldset>';
        $this->echoSettingsInput(
            'einsatzvw_rewrite_slug',
            sprintf(
                'Basis f&uuml;r Links zu Einsatzberichten, zum %1$sArchiv%2$s und zum %3$sFeed%2$s.',
                '<a href="' . get_post_type_archive_link('einsatz') . '">',
                '</a>',
                '<a href="' . get_post_type_archive_feed_link('einsatz') . '">'
            ),
            sanitize_title(get_option('einsatzvw_rewrite_slug'), 'einsatzberichte')
        );
        echo '</fieldset>';
    }

    public function echoFieldAnnotations()
    {
        echo '<fieldset>';
        echo '<p>Farbe f&uuml;r inaktive Vermerke:</p>';
        $this->echoColorPicker('einsatzvw_list_annotations_color_off', AnnotationIconBar::DEFAULT_COLOR_OFF);
        echo '<p class="description">Diese Farbe wird f&uuml;r die Symbole von inaktiven Vermerken verwendet, die von aktiven werden in der Textfarbe Deines Themes dargestellt.</p>';
        echo '</fieldset>';
    }

    /**
     * Prüft, ob sich die Basis für die Links zu Einsatzberichten ändert und veranlasst gegebenenfalls ein Erneuern der
     * Permalinkstruktur
     *
     * @param string $newValue Der neue Wert
     * @param string $oldValue Der alte Wert
     * @return string Der zu speichernde Wert
     */
    public function maybeRewriteSlugChanged($newValue, $oldValue)
    {
        if ($newValue != $oldValue) {
            self::$options->setFlushRewriteRules(true);
        }

        return $newValue;
    }

    /**
     * Prüft, ob sich die Kategorie der Einsatzberichte ändert und veranlasst gegebenenfalls ein Erneuern der
     * Kategoriezuordnung
     *
     * @param string $newValue Der neue Wert
     * @param string $oldValue Der alte Wert
     *
     * @return string Der zu speichernde Wert
     */
    public function maybeCategoryChanged($newValue, $oldValue)
    {
        // Nur Änderungen sind interessant
        if ($newValue == $oldValue) {
            return $newValue;
        }

        $posts = get_posts(array(
            'post_type' => 'einsatz',
            'post_status' => array('publish', 'private'),
            'numberposts' => -1
        ));
        $reports = Utilities::postsToIncidentReports($posts);

        // Wenn zuvor eine Kategorie gesetzt war, müssen die Einsatzberichte aus dieser entfernt werden
        if ($oldValue != -1) {
            /** @var IncidentReport $report */
            foreach ($reports as $report) {
                Utilities::removePostFromCategory($report->getPostId(), $oldValue);
            }
        }

        // Wenn eine neue Kategorie gesetzt wird, müssen Einsatzberichte dieser zugeordnet werden
        if ($newValue != -1) {
            $onlySpecialInCategory = self::$options->isOnlySpecialInLoop();
            /** @var IncidentReport $report */
            foreach ($reports as $report) {
                if (!$onlySpecialInCategory || $report->isSpecial()) {
                    Utilities::addPostToCategory($report->getPostId(), $newValue);
                }
            }
        }

        return $newValue;
    }

    /**
     * Prüft, ob sich die Beschränkung, nur als besonders markierte Einsatzberichte der Kategorie zuzuordnen, ändert
     * und veranlasst gegebenenfalls ein Erneuern der Kategoriezuordnung
     *
     * @param string $newValue Der neue Wert
     * @param string $oldValue Der alte Wert
     *
     * @return string Der zu speichernde Wert
     */
    public function maybeCategorySpecialChanged($newValue, $oldValue)
    {
        // Nur Änderungen sind interessant
        if ($newValue == $oldValue) {
            return $newValue;
        }

        // Ohne gesetzte Kategorie brauchen wir nicht weitermachen
        $categoryId = self::$options->getEinsatzberichteCategory();
        if (-1 === $categoryId) {
            return $newValue;
        }

        $posts = get_posts(array(
            'post_type' => 'einsatz',
            'post_status' => array('publish', 'private'),
            'numberposts' => -1
        ));
        $reports = Utilities::postsToIncidentReports($posts);

        // Wenn die Einstellung abgewählt wurde, werden alle Einsatzberichte zur Kategorie hinzugefügt
        if ($newValue == 0) {
            /** @var IncidentReport $report */
            foreach ($reports as $report) {
                Utilities::addPostToCategory($report->getPostId(), $categoryId);
            }
        }

        // Wenn die Einstellung aktiviert wurde, werden nur die als besonders markierten Einsatzberichte zur Kategorie
        // hinzugefügt, alle anderen daraus entfernt
        if ($newValue == 1) {
            /** @var IncidentReport $report */
            foreach ($reports as $report) {
                if ($report->isSpecial()) {
                    Utilities::addPostToCategory($report->getPostId(), $categoryId);
                } else {
                    Utilities::removePostFromCategory($report->getPostId(), $categoryId);
                }
            }
        }

        return $newValue;
    }

    public function registerSettings()
    {
        register_setting(
            'einsatzvw_settings_general',
            'einsatzvw_rewrite_slug',
            'sanitize_title'
        );
        register_setting(
            'einsatzvw_settings_general',
            'einsatzvw_show_einsatzberichte_mainloop',
            array('Utilities', 'sanitizeCheckbox')
        );
        register_setting(
            'einsatzvw_settings_general',
            'einsatzvw_category',
            'intval'
        );
        register_setting(
            'einsatzvw_settings_general',
            'einsatzvw_loop_only_special',
            array('Utilities', 'sanitizeCheckbox')
        );
        register_setting(
            'einsatzvw_settings_general',
            'einsatzvw_list_annotations_color_off',
            array($this, 'sanitizeAnnotationOffColor') // NEEDS_WP4.6 das globale sanitize_hex_color() verwenden
        );
    }

    /**
     * Stellt sicher, dass die Farbe für die inaktiven Vermerke gültig ist
     *
     * @param string $input Der zu prüfende Farbwert
     *
     * @return string Der übergebene Farbwert, wenn er gültig ist, ansonsten die Standardeinstellung
     */
    public function sanitizeAnnotationOffColor($input)
    {
        return Utilities::sanitizeHexColor($input, AnnotationIconBar::DEFAULT_COLOR_OFF);
    }
}
