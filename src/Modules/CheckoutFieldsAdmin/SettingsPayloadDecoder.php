<?php
/**
 * Decode checkout-field settings requests.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\CheckoutFieldsAdmin;

use JsonException;
use UnexpectedValueException;

/**
 * Rejects oversized or malformed JSON before schema sanitization.
 */
final class SettingsPayloadDecoder {

	private const MAX_BYTES = 1048576;

	/**
	 * Decode one settings JSON object.
	 *
	 * @param string $payload Raw JSON payload.
	 * @return array<mixed>
	 *
	 * @throws UnexpectedValueException When the payload is invalid.
	 */
	public function decode( string $payload ): array {
		if ( self::MAX_BYTES < strlen( $payload ) ) {
			throw new UnexpectedValueException( 'too_large' );
		}

		try {
			$settings = json_decode( $payload, true, 12, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			throw new UnexpectedValueException( 'invalid_json' );
		}

		if ( ! is_array( $settings ) ) {
			throw new UnexpectedValueException( 'invalid_shape' );
		}

		return $settings;
	}
}
