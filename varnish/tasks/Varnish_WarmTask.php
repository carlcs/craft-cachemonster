<?php
namespace Craft;

/**
 * Varnish by Supercool
 *
 * @package   Varnish
 * @author    Josh Angell
 * @copyright Copyright (c) 2015, Supercool Ltd
 * @link      http://plugins.supercooldesign.co.uk
 */

class Varnish_WarmTask extends BaseTask
{

	// Properties
	// =========================================================================

	/**
	 * @var
	 */
	private $_paths;


	// Public Methods
	// =========================================================================


	/**
	 * @inheritDoc ITask::getDescription()
	 *
	 * @return string
	 */
	public function getDescription()
	{
		return Craft::t('Warming the Varnish cache');
	}

	/**
	 * @inheritDoc ITask::getTotalSteps()
	 *
	 * @return int
	 */
	public function getTotalSteps()
	{
		// Get the actual paths out of the settings
		$paths = $this->getSettings()->paths;

		// Make our internal paths array
		$this->_paths = array();

		// Split the $paths array into chunks of 20 - each step
		// will be a batch of 20 requests
		$num = ceil(count($paths) / 20);
		for ($i=0; $i < $num; $i++)
		{
			$this->_paths[] = array_slice($paths, $i, 20);
		}

		// Count our final chunked array
		return count($this->_paths);
	}

	/**
	 * @inheritDoc ITask::runStep()
	 *
	 * @param int $step
	 *
	 * @return bool
	 */
	public function runStep($step)
	{

		// NOTE: Perhaps much of this should be moved into a service

		$batch = \Guzzle\Batch\BatchBuilder::factory()
						->transferRequests(20)
						->bufferExceptions()
						->build();

		// Make the client
		$client = new \Guzzle\Http\Client();

		// Set the Accept header
		$client->setDefaultOption('headers/Accept', '*/*');

		// Loop the paths in this step
		foreach ($this->_paths[$step] as $path)
		{

			// Make the url, stripping 'site:' from the path
			$newPath = preg_replace('/site:/', '', $path, 1);
			$url = UrlHelper::getSiteUrl($newPath);

			Craft::log('Adding URL: '.$url, LogLevel::Error, true);

			// Create the GET request
			$request = $client->get($url);

			// Add it to the batch
			$batch->add($request);

		}

		// Flush the queue and retrieve the flushed items
		$requests = $batch->flush();

		// Clear any exceptions, we could log these
		// via $batch->getExceptions() if we wanted to
		$batch->clearExceptions();

		return true;

	}

	// Protected Methods
	// =========================================================================

	/**
	 * @inheritDoc BaseSavableComponentType::defineSettings()
	 *
	 * @return array
	 */
	protected function defineSettings()
	{
		return array(
			'paths'  => AttributeType::Mixed
		);
	}

	// Private Methods
	// =========================================================================

}
