<?php
namespace abrain\Einsatzverwaltung\Widgets;

use abrain\Einsatzverwaltung\Util\Formatter;
use abrain\Einsatzverwaltung\Utilities;
use WP_Widget;

/**
 * Widget für die neuesten Einsätze, das Aussehen wird vom Benutzer per HTML-Templates bestimmt
 *
 * @author Andreas Brain
 */
class RecentIncidentsFormatted extends WP_Widget
{
    /**
     * @var Formatter
     */
    private $formatter;

    /**
     * @var Utilities
     */
    private $utilities;

    private $allowedHtmlTags = array(
        'a' => array(
            'href' => true,
            'rel' => true,
            'rev' => true,
            'name' => true,
            'style' => true,
            'target' => true,
        ),
        'abbr' => array(),
        'acronym' => array(),
        'br' => array(),
        'div' => array(
            'align' => true,
            'class' => true,
            'dir' => true,
            'lang' => true,
            'style' => true,
            'xml:lang' => true,
        ),
        'h3' => array(
            'align' => true,
            'style' => true
        ),
        'h4' => array(
            'align' => true,
            'style' => true
        ),
        'h5' => array(
            'align' => true,
            'style' => true
        ),
        'h6' => array(
            'align' => true,
            'style' => true
        ),
        'hr' => array(
            'align' => true,
            'noshade' => true,
            'size' => true,
            'style' => true,
            'width' => true,
        ),
        'i' => array(
            'class' => true,
            'title'=> true,
            'style' => true
        ),
        'img' => array(
            'alt' => true,
            'align' => true,
            'border' => true,
            'class' => true,
            'height' => true,
            'hspace' => true,
            'longdesc' => true,
            'vspace' => true,
            'src' => true,
            'style' => true,
            'width' => true,
        ),
        'li' => array(
            'align' => true,
            'class' => true,
            'style' => true,
            'value' => true,
        ),
        'p' => array(
            'align' => true,
            'class' => true,
            'dir' => true,
            'lang' => true,
            'style' => true,
            'xml:lang' => true,
        ),
        'span' => array(
            'dir' => true,
            'align' => true,
            'class' => true,
            'lang' => true,
            'style' => true,
            'xml:lang' => true,
        ),
        'ul' => array(
            'class' => true,
            'style' => true,
            'type' => true,
        ),
        'ol' => array(
            'class' => true,
            'start' => true,
            'style' => true,
            'type' => true,
        ),
    );
    private $defaults = array(
        'title' => '',
        'numIncidents' => 3,
        'beforeContent' => '',
        'pattern' => '',
        'afterContent' => ''
    );
    private $allowedTagsPattern = array('%title%', '%date%', '%time%', '%location%', '%duration%', '%incidentType%',
        '%incidentTypeColor%', '%url%', '%number%', '%seqNum%', '%annotations%', '%vehicles%', '%additionalForces%',
        '%typesOfAlerting%', '%featuredImage%');
    private $allowedTagsAfter = array('%feedUrl%');

    /**
     * Konstruktor, generiert und registriert das Widget
     * @param Formatter $formatter
     * @param Utilities $utilities
     */
    public function __construct(Formatter $formatter, Utilities $utilities)
    {
        parent::__construct(
            'recent-incidents-formatted',
            'Letzte Eins&auml;tze (eigenes Format)',
            array(
                'description' => 'Zeigt die neuesten Eins&auml;tze an.' . ' ' .
                    'Das Aussehen kann vollst&auml;ndig mit eigenem HTML bestimmt werden.',
                'customize_selective_refresh' => true,
            )
        );
        $this->formatter = $formatter;
        $this->utilities = $utilities;
    }

    /**
     * Die Ausgabe des Widgetinhalts
     *
     * @param array $args     Anzeigeargumente, u.a. before_title, after_title, before_widget und after_widget.
     * @param array $instance Die Einstellungen der betreffenden Instanz des Widgets.
     */
    public function widget($args, $instance)
    {
        $settings = wp_parse_args($instance, $this->defaults);

        if (empty($settings['title'])) {
            $settings['title'] = 'Letzte Eins&auml;tze';
        }

        if (empty($settings['numIncidents'])) {
            $settings['numIncidents'] = $this->defaults['numIncidents'];
        }

        echo $args['before_widget'];
        echo $args['before_title'] . apply_filters('widget_title', $settings['title']) . $args['after_title'];

        $incidents = get_posts(array(
            'post_type' => 'einsatz',
            'post_status' => 'publish',
            'posts_per_page' => $settings['numIncidents']
        ));

        $widgetContent = $settings['beforeContent'];
        foreach ($incidents as $incident) {
            $widgetContent .= $this->formatter->formatIncidentData($settings['pattern'], $this->allowedTagsPattern, $incident, 'widget');
        }
        $widgetContent .= $this->formatter->formatIncidentData($settings['afterContent'], $this->allowedTagsAfter, null, 'widget');

        echo wp_kses($widgetContent, $this->allowedHtmlTags);
        echo $args['after_widget'];
    }

    /**
     * Eine bestimmte Instanz des Widgets aktualisieren
     *
     * @param array $newInstance Die neuen Einstellungen
     * @param array $oldInstance Die bisherigen Einstellungen
     *
     * @return array Die zu speichernden Einstellungen oder false um das Speichern abzubrechen
     */
    public function update($newInstance, $oldInstance)
    {
        $instance = array();
        $instance['title'] = strip_tags($newInstance['title']);
        $instance['numIncidents'] = $this->utilities->sanitizeNumberGreaterZero(
            $newInstance['numIncidents'],
            $this->defaults['numIncidents']
        );
        $instance['beforeContent'] = wp_kses($newInstance['beforeContent'], $this->allowedHtmlTags);
        $instance['pattern'] = wp_kses($newInstance['pattern'], $this->allowedHtmlTags);
        $instance['afterContent'] = wp_kses($newInstance['afterContent'], $this->allowedHtmlTags);

        return $instance;
    }

    /**
     * Gibt das Formular für die Einstellungen aus.
     *
     * @param array $instance Derzeitige Einstellungen.
     *
     * @return string HTML-Code für das Formular
     */
    public function form($instance)
    {
        echo '<p>';
        printf(
            '<label for="%1$s">%2$s</label><input class="widefat" id="%1$s" name="%3$s" type="text" value="%4$s" />',
            $this->get_field_id('title'),
            'Titel:',
            $this->get_field_name('title'),
            esc_attr($this->utilities->getArrayValueIfKey($instance, 'title', ''))
        );
        echo '</p>';

        $numIncidents = $this->utilities->getArrayValueIfKey($instance, 'numIncidents', $this->defaults['numIncidents']);
        echo '<p>';
        printf(
            '<label for="%1$s">%2$s</label>&nbsp;<input id="%1$s" name="%3$s" type="text" value="%4$s" size="3" />',
            $this->get_field_id('numIncidents'),
            'Anzahl der Einsatzberichte, die angezeigt werden:',
            $this->get_field_name('numIncidents'),
            empty($numIncidents) ? $this->defaults['numIncidents'] : esc_attr($numIncidents)
        );
        echo '</p>';

        echo '<p>';
        printf(
            '<label for="%1$s">%2$s</label><textarea class="widefat" id="%1$s" name="%3$s">%4$s</textarea>',
            $this->get_field_id('beforeContent'),
            'HTML-Code vor den Einsatzberichten:',
            $this->get_field_name('beforeContent'),
            $this->utilities->getArrayValueIfKey($instance, 'beforeContent', '')
        );
        echo '</p>';

        echo '<p>';
        printf(
            '<label for="%1$s">%2$s</label><textarea class="widefat" id="%1$s" name="%3$s">%4$s</textarea>',
            $this->get_field_id('pattern'),
            'HTML-Template pro Einsatzbericht:',
            $this->get_field_name('pattern'),
            $this->utilities->getArrayValueIfKey($instance, 'pattern', '')
        );
        echo '</p><p class="description">' . 'Folgende Tags werden ersetzt:';
        $formatterTags = $this->formatter->getTags();
        foreach ($this->allowedTagsPattern as $tag) {
            echo '<br>' . $tag . ' (' . $formatterTags[$tag] . ')';
        }
        echo '</p>';

        echo '<p>';
        printf(
            '<label for="%1$s">%2$s</label><textarea class="widefat" id="%1$s" name="%3$s">%4$s</textarea>',
            $this->get_field_id('afterContent'),
            'HTML-Code nach den Einsatzberichten:',
            $this->get_field_name('afterContent'),
            $this->utilities->getArrayValueIfKey($instance, 'afterContent', '')
        );
        echo '</p><p class="description">' . 'Folgende Tags werden ersetzt:';
        foreach ($this->allowedTagsAfter as $tag) {
            echo '<br>' . $tag . ' (' . $formatterTags[$tag] . ')';
        }
        echo '</p>';

        return '';
    }
}
