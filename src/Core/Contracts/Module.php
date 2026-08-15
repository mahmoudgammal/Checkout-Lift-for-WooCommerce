<?php
/**
 * Contract implemented by independently bootable plugin modules.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts;

/**
 * Defines the minimum lifecycle required by the module registry.
 */
interface Module {

	/**
	 * Return the stable module identifier.
	 *
	 * @return non-empty-string
	 */
	public function id(): string;

	/**
	 * Return module identifiers that must boot first.
	 *
	 * @return list<non-empty-string>
	 */
	public function dependencies(): array;

	/**
	 * Register this module's hooks and services.
	 */
	public function boot(): void;
}
