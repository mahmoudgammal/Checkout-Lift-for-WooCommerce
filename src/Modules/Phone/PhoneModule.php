<?php
/**
 * Phone feature composition entry.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Modules\Phone;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module;

/**
 * Registers the Phone module's classic-checkout adapters.
 */
final class PhoneModule implements Module {

	/**
	 * Create the Phone module.
	 *
	 * @param PhoneAssets              $assets Phone asset adapter.
	 * @param ClassicCheckoutValidator $validator Classic checkout validation adapter.
	 */
	public function __construct(
		private PhoneAssets $assets,
		private ClassicCheckoutValidator $validator
	) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'phone';
	}

	/**
	 * {@inheritDoc}
	 */
	public function dependencies(): array {
		return array();
	}

	/**
	 * {@inheritDoc}
	 */
	public function boot(): void {
		$this->assets->register_hooks();
		$this->validator->register_hooks();
	}
}
