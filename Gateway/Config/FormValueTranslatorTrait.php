<?php
namespace Fiserv\Payments\Gateway\Config;

/**
 * Shared helpers for translating SDC v2 iframe form configuration values
 * (placeholders, invalid messages, option labels, etc).
 */
trait FormValueTranslatorTrait
{
	/**
	 * Translates a single scalar form config value.
	 */
	private function translateFormValue($value)
	{
		return $value === null ? null : (string)__((string)$value);
	}

	/**
	 * Translates a JSON-encoded array of option labels.
	 */
	private function translateOptionLabels($value)
	{
		$labels = json_decode((string)$value, true);
		if (!is_array($labels)) {
			return $value;
		}

		return json_encode(array_map(function ($label) {
			return $this->translateFormValue($label);
		}, $labels), JSON_UNESCAPED_UNICODE);
	}
}

