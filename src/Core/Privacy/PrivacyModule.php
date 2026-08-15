<?php
/**
 * Privacy and retention module.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Privacy;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module;

/**
 * Registers everything that makes stored checkout activity accountable:
 * scheduled deletion, export, and erasure.
 */
final class PrivacyModule implements Module {

	/**
	 * Create the privacy module.
	 *
	 * @param RetentionSweep       $sweep    Scheduled deletion.
	 * @param PersonalDataExporter $exporter Export handler.
	 * @param PersonalDataEraser   $eraser   Erasure handler.
	 */
	public function __construct(
		private RetentionSweep $sweep,
		private PersonalDataExporter $exporter,
		private PersonalDataEraser $eraser
	) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'privacy';
	}

	/**
	 * {@inheritDoc}
	 */
	public function dependencies(): array {
		return array( 'database' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot(): void {
		add_action( RetentionSweep::HOOK, array( $this->sweep, 'run' ) );

		// Scheduling touches options, so it is kept off customer requests.
		add_action( 'admin_init', array( $this->sweep, 'schedule' ) );

		add_filter( 'wp_privacy_personal_data_exporters', array( $this->exporter, 'register' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this->eraser, 'register' ) );
	}
}
