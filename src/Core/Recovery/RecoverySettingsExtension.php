<?php
/**
 * Recovery settings extension contract.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Recovery;

/**
 * Adds package-owned fields to the shared recovery settings endpoint.
 */
interface RecoverySettingsExtension {

	/**
	 * Return current package-owned values.
	 *
	 * @return array<string, mixed>
	 */
	public function values(): array;

	/**
	 * Return REST schemas for package-owned fields.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function args(): array;

	/**
	 * Store package-owned submitted fields.
	 *
	 * @param array<string, mixed> $values Validated request values.
	 */
	public function replace( array $values ): bool;
}
