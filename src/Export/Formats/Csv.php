<?php
namespace abrain\Einsatzverwaltung\Export\Formats;

use abrain\Einsatzverwaltung\Model\IncidentReport;
use abrain\Einsatzverwaltung\Data;

require_once dirname(__FILE__) . '/AbstractFormat.php';

/**
 * Exportiert Einsatzberichte in eine CSV-Datei.
 */
class Csv extends AbstractFormat
{
    /**
     * @var string
     */
    protected $delimiter;

    /**
     * @var string
     */
    protected $enclosure;

    /**
     * @var string
     */
    protected $escapeChar;

    /**
     * @var boolean
     */
    protected $headers;

    /**
     * @inheritDoc
     */
    public function getTitle()
    {
        return 'CSV';
    }

    /**
     * @inheritDoc
     */
    public function renderOptions()
    {
        ?>
        <li>
            <label>
                <span class="label-responsive">Spalten getrennt mit:</span>
                <input name="export_options[csv][delimiter]" type="text" value="," required="required">
            </label>
        </li>
        <li>
            <label>
                <span class="label-responsive">Spalten eingeschlossen von:</span>
                <input name="export_options[csv][enclosure]" type="text" value="&quot;" required="required">
            </label>
        </li>
        <li>
            <label>
                <span class="label-responsive">Spalten escaped mit:</span>
                <input name="export_options[csv][escapeChar]" type="text" value=";" required="required">
            </label>
        </li>
        <li>
            <input type="checkbox" name="export_options[csv][headers]" id="csv_headers" value="1" checked="checked">
            <label for="csv_headers">Spaltennamen in die erste Zeile setzen</label>
        </li>
<?php
    }

    /**
     * @inheritDoc
     */
    public function setOptions(array $options)
    {
        $this->delimiter = @$options['delimiter'];
        if (empty($this->delimiter)) {
            $this->delimiter = ',';
        }
        $this->enclosure = @$options['enclosure'];
        if (empty($this->enclosure)) {
            $this->enclosure = '"';
        }
        $this->escapeChar = @$options['escapeChar'];
        if (empty($this->escapeChar)) {
            $this->escapeChar = '\\';
        }
        $this->headers = (boolean)@$options['headers'];
    }

    /**
     * @inheritDoc
     */
    public function getFilename()
    {
        return 'Einsatzberichte.csv';
    }

    /**
     * @inheritDoc
     */
    public function export()
    {
        $fh = fopen('php://output', 'w');
        // füge BOM hinzu, damit UTF-8-formatierte Inhalte in Excel funktionieren.
        // siehe: http://php.net/manual/de/function.fputcsv.php#118252
        fputs($fh, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // füge ggf. Spaltennamen als die erste Zeile ein
        if ($this->headers) {
            $data = array(
             'Einsatznummer',
             'Alarmierungsart',
             'Alarmzeit',
             'Einsatzende',
             'Dauer (Minuten)',
             'Einsatzort',
             'Einsatzart',
             'Fahrzeuge',
             'Externe Einsatzmittel',
             'Mannschaftsstärke',
             'Einsatzleiter',
             'Berichtstitel',
             'Berichtstext',
             'Besonderer Einsatz',
             'Fehlalarm'
          );
            fputcsv($fh, $data, $this->delimiter, $this->enclosure, $this->escapeChar);
        }

        $query = $this->getQuery();
        while ($query->have_posts()) {
            $post = $query->next_post();
            $report = new IncidentReport($post);
            
            $duration = Data::getDauer($report);
            if (!$duration) {
                $duration = 0;
            }

            $typeOfIncident = $report->getTypeOfIncident();
            if (!$typeOfIncident) {
                $typeOfIncident = '';
            }

            $data = array(
               $report->getSequentialNumber(),
               implode(',', $report->getTypesOfAlerting()),
               $report->getTimeOfAlerting()->format('Y-m-d H:i'),
               $report->getTimeOfEnding(),
               $duration,
               $report->getLocation(),
               $typeOfIncident,
               implode(',', $report->getVehicles()),
               implode(',', $report->getAdditionalForces()),
               $report->getWorkforce(),
               $report->getIncidentCommander(),
               $post->post_title,
               $post->post_content,
               ($report->isSpecial() ? 'Ja' : 'Nein'),
               ($report->isFalseAlarm() ? 'Ja' : 'Nein'),
            );
            fputcsv($fh, $data, $this->delimiter, $this->enclosure, $this->escapeChar);
        }

        fclose($fh);
    }
}
