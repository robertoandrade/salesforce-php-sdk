<?php

/**
 * External Data Service for Wave.
 *
 * @license http://opensource.org/licenses/MIT The MIT License (MIT)
 */

namespace Justia\SalesforcePhpSdk;

/**
 * External Data Service for Wave.
 *
 * The Wave connected application must have the following permission :
 * * Access and manage your data (api)
 *
 * Or it won't be able to create
 * InsightsExternalData and InsightsExternalDataPart SObjects
 * (i.e. this service will not work).
 */
class SforceWaveClient
{
    /**
     * Salesforce SObjects service.
     *
     * This is used for InsightsExternalData and InsightsExternalDataPart
     * creations.
     *
     * @var SforceEnterpriseClient
     */
    protected $sobjects;

    /**
     * The InsightsExternalData object.
     *
     * This is used to store the state of the Dataset after a call to create().
     *
     * @var InsightsExternalData
     */
    protected $externalData;

    /**
     * The many InsightsExternalDataPart objects.
     *
     * This is used to store the state of the Dataset after a call to create().
     *
     * @var InsightsExternalDataPart[]
     */
    protected $parts;

    /**
     * Wave external data service constructor.
     *
     * @param SforceEnterpriseClient $sobjects The Salesforce SObjects service.
     */
    public function __construct(
        SforceEnterpriseClient $sobjects
    ) {
        $this->sobjects = $sobjects;
    }

    /**
     * Get the Salesforce SObjects service.
     *
     * @return SalesforceSObjectsService The Salesforce SObjects service.
     */
    protected function getSObjects()
    {
        return $this->sobjects;
    }

    /**
     * Get the InsightsExternalData object.
     *
     * The InsightsExternalData object is initialized after a call to create().
     * Before the call to create() it is unset. After the call to create() it is
     * an instance of InsightsExternalData.
     *
     * @return InsightsExternalData The external data object.
     */
    public function getExternalData()
    {
        return $this->externalData;
    }

    /**
     * Get the InsightsExternalDataPart array.
     *
     * The InsightsExternalDataPart array is initialized after a call to create().
     * Before the call to create() it is unset. After the call to create() it is
     * an array() containing one instance of InsightsExternalDataPart.
     *
     * @return array The InsightsExternalDataPart array.
     */
    public function getParts()
    {
        return $this->parts;
    }

    /**
     * Get the last part in the InsightsExternalDataPart array.
     *
     * @return InsightsExternalDataPart The last part.
     */
    public function getLastPart()
    {
        return $this->parts[count($this->parts)-1];
    }

    /**
     * Create an InsightsExternalData Dataset.
     *
     * @param string  $edgemartLabel The required InsightsExternalData label.
     * @param array   $config        The Dataset configuration.
     * @return SforceWaveClient A new instance of the Wave External Data
     *                                 service initialized for a new Dataset.
     */
    public function create(
        $edgemartLabel,
        $config = null
    ) {
        /**
         * Creating an orphan Wave External Data service with same config as the
         * current instance.
         */
        $dataset = new SforceWaveClient(
            $this->sobjects
        );

        $dataset->init($edgemartLabel, $config);

        return $dataset;
    }

    /**
     * Initialize the InsightsExternalData Dataset.
     *
     * Please leave this method protected. By being protected this method cannot
     * be called from outside of the SforceWaveClient class, so outside
     * people cannot call this method twice (which would break things). The cool
     * thing is, in create() we are already inside an SforceWaveClient so
     * we can call this protected method even on a new instance of
     * SforceWaveClient.
     *
     * @param string  $edgemartLabel The required InsightsExternalData label.
     * @param array   $config        The Dataset configuration.
     */
    protected function init($edgemartLabel, $config)
    {
        $this->createExternalData($edgemartLabel, $config);
        $this->createPart();
    }

    /**
     * Create the InsightsExternalData.
     *
     * This method configures and creates the InsightsExternalData instance.
     *
     * @param string  $edgemartLabel The required InsightsExternalData label.
     * @param array   $config        The Dataset configuration.
     */
    protected function createExternalData($edgemartLabel, $config)
    {
        if (isset($this->externalData)) {
            throw new \Exception(
                'There can be only one InsightsExternalData object '
                . 'per Dataset.'
            );
        }

        $this->externalData = new InsightsExternalData();

        $this->getExternalData()->setEdgemartLabel($edgemartLabel);
        $this->configure($config);

        $id = $this->pushExternalData();

        $this->getExternalData()->setId($id[0]->id);
    }

    /**
     * Configure the current Dataset.
     *
     * @param  array $config The configuration.
     */
    public function configure($config = null)
    {
        $defaults = array(
            'edgemartAlias' => null,
            'edgemartContainer' => null,
            'metadataJson' => null,
            'format' => 'Csv', // <= required
            'operation' => 'Overwrite', // <= required
            'action' => 'None', // <= required
            'notificationSent' => 'Never',
            'notificationEmail' => null,
            'fileName' => null,
            'description' => null
        );

        $_config = array();
        $keys = array();

        // apply defaults and save possible keys
        foreach ($defaults as $key => $value) {
            array_push($keys, $key);

            if ($value !== null) {
                $_config[$key] = $value;
            }
        }

        if ($config !== null) {
            $invalidKeys = array();

            // apply custom values and check invalid keys
            foreach ($config as $key => $value) {
                if (!in_array($key, $keys)) {
                    array_push($invalidKeys, $key);
                } else {
                    $_config[$key] = $value;
                }
            }

            // check invalid keys
            if (count($invalidKeys) > 0) {
                throw new \Exception(
                    'Invalid ExternalData configuration keys : '
                    . implode(', ', $invalidKeys)
                );
            }
        }

        // apply configuration to external data object
        foreach ($_config as $key => $value) {
            call_user_func(
                array($this->getExternalData(), 'set' . ucfirst($key)),
                $value
            );
        }
    }

    /**
     * Push the InsightsExternalData object to Salesforce.
     *
     * @return array The Salesforce create response.
     */
    protected function pushExternalData()
    {
        $externalData = $this->getExternalData();

        return SforceBaseClient::ensureSuccess(
            $this->getSObjects()->create(
                array(
                    (object) array(
                        'EdgemartLabel' => $externalData->getEdgemartLabel(),
                        'EdgemartAlias' => $externalData->getEdgemartAlias(),
                        'EdgemartContainer' => $externalData->getEdgemartContainer(),
                        'MetadataJson' => $externalData->getMetadataJson(),
                        'Format' => $externalData->getFormat(), // <= required
                        'Operation' => $externalData->getOperation(), // <= required
                        'Action' => $externalData->getAction(), // <= required
                        'NotificationSent' => $externalData->getNotificationSent(),
                        'NotificationEmail' => $externalData->getNotificationEmail(),
                        'FileName' => $externalData->getFileName(),
                        'Description' => $externalData->getDescription()
                    )
                ),
                'InsightsExternalData'
            )
        );
    }

    /**
     * Create a new InsightsExternalDataPart.
     *
     * The last part is sent to Salesforce before creating the new Part.
     */
    protected function createPart()
    {
        if (!isset($this->parts)) {
            $this->parts = array();
            $partNumber = 1;
        } else {
            $partNumber = $this->getLastPart()->getPartNumber() + 1;
            $this->pushLastPart();
        }

        $part = new InsightsExternalDataPart();
        $part->setInsightsExternalDataId($this->getExternalData()->getId());
        $part->setPartNumber($partNumber);
        array_push($this->parts, $part);
    }

    /**
     * Push the last InsightsExternalDataPart object to Salesforce.
     *
     * @return array The Salesforce create response.
     */
    protected function pushLastPart()
    {
        $part = $this->getLastPart();

        if ($part->getDataFile() === null) {
            return;
        }

        return SforceBaseClient::ensureSuccess(
            $this->getSObjects()->create(
                array(
                    (object) array(
                        'PartNumber' => $part->getPartNumber(),
                        'InsightsExternalDataId' => $part->getInsightsExternalDataId(),
                        'DataFile' => $part->getDataFile()
                    )
                ),
                'InsightsExternalDataPart'
            )
        );
    }

    /**
     * Append some raw string to the InsightsExternalData.
     *
     * The raw string is NOT escaped for Csv.
     *
     * @param string  $data    The raw string to append.
     * @param boolean $gzipped Whether the data is already gzipped or not.
     * @param boolean $encoded Whether the data is already Base64-encoded or not.
     * @return SforceWaveClient The current instance of SforceWaveClient
     *                                 to enable methods chaining.
     */
    public function appendRaw($data, $gzipped = false, $encoded = false)
    {
        if (!isset($this->externalData)) {
            throw new \Exception(
                'Cannot append data to Dataset. '
                . 'Create a Dataset first with create().'
            );
        }

        if (!$gzipped) {
            $data = gzencode($data);
        }

        if (!$encoded) {
            $data = base64_encode($data);
        }

        while ($data !== false) {
            // fill the void in the last existing part, max 10MB

            $dataFile = $this->getLastPart()->getDataFile();
            $void = 10000 - mb_strlen($dataFile, '8bit');

            if ($void > 0) {
                $part = substr($data, 0, $void);
                $data = substr($data, $void, mb_strlen($data, '8bit') - $void);

                $this->getLastPart()->setDataFile($dataFile . $part);
            }

            // prepare a new part if we reached the 10MB max

            if (mb_strlen($dataFile, '8bit') === 10000) {
                $this->createPart();
            }
        }

        return $this;
    }

    /**
     * Append a csv line from an array to the Dataset.
     *
     * A newline character is automatically added at the end of the line.
     *
     * @param array $fields An array of values
     * @return SforceWaveClient The current instance of SforceWaveClient
     *                                 to enable methods chaining.
     */
    public function appendCsvLine(array $fields)
    {
        if (!isset($this->externalData)) {
            throw new \Exception(
                'Cannot append csv line to Dataset. '
                . 'Create a Dataset first with create().'
            );
        }

        ob_start();
        $out = fopen('php://output', 'w');
        fputcsv($out, $fields);
        fclose($out);
        $data = ob_get_clean();

        $this->appendRaw($data, false, false);

        return $this;
    }

    /**
     * Append many csv lines to the Dataset.
     *
     * This is significantly faster than calling appendCsvLine() in a loop.
     *
     * @param array $dataset An array of array of values.
     * @return SforceWaveClient The current instance of SforceWaveClient
     *                                 to enable methods chaining.
     */
    public function appendCsvLines(array $dataset)
    {
        if (!isset($this->externalData)) {
            throw new \Exception(
                'Cannot append csv lines to Dataset. '
                . 'Create a Dataset first with create().'
            );
        }

        ob_start();
        $out = fopen('php://output', 'w');
        foreach ($dataset as $fields) {
            fputcsv($out, $fields);

            if (ob_get_length() > 10000) {
                $data = ob_get_clean();
                $this->appendRaw($data);
                ob_start();
            }
        }
        fclose($out);

        $data = ob_get_clean();
        $this->appendRaw($data);

        return $this;
    }

    /**
     * Process the External Data DataSet.
     */
    public function process()
    {
        if (!isset($this->externalData)) {
            throw new \Exception(
                'Cannot process Dataset. '
                . 'Create a Dataset first with create().'
            );
        }

        $this->pushLastPart();

        $externalData = $this->getExternalData();
        $externalData->setAction('Process');

        return SforceBaseClient::ensureSuccess(
            $this->getSObjects()->update(
                array(
                    (object) array(
                        'Id'     => $externalData->getId(),
                        'Action' => $externalData->getAction()
                    )
                ),
                'InsightsExternalData'
            )
        );
    }
}
