<?php
/**
 * Deterministic plugin module registration and boot order.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module;
use DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\ModuleProvider;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

/**
 * Registers modules explicitly and boots their dependency graph.
 */
final class ModuleRegistry {

	/**
	 * Registered modules keyed by their stable identifiers.
	 *
	 * @var array<string, Module>
	 */
	private array $modules = array();

	/**
	 * Validated dependency identifiers keyed by module identifier.
	 *
	 * @var array<string, list<string>>
	 */
	private array $dependencies = array();

	/**
	 * Modules that completed their boot method.
	 *
	 * @var array<string, true>
	 */
	private array $booted = array();

	/**
	 * Register one module.
	 *
	 * @param Module $module Module to register.
	 *
	 * @throws InvalidArgumentException When the module identifier is invalid.
	 * @throws RuntimeException         When the identifier is already registered.
	 */
	public function register( Module $module ): void {
		$module_id = $module->id();

		if ( ! $this->is_valid_id( $module_id ) ) {
			throw new InvalidArgumentException( 'Module IDs must use lowercase letters, numbers, and hyphens.' );
		}

		if ( isset( $this->modules[ $module_id ] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception text is never rendered.
			throw new RuntimeException( sprintf( 'Module "%s" is already registered.', $module_id ) );
		}

		$dependencies = $module->dependencies();

		foreach ( $dependencies as $dependency_id ) {
			if ( ! is_string( $dependency_id ) || ! $this->is_valid_id( $dependency_id ) ) {
				throw new InvalidArgumentException( 'Dependency IDs must use lowercase letters, numbers, and hyphens.' );
			}
		}

		$this->modules[ $module_id ]      = $module;
		$this->dependencies[ $module_id ] = array_values( array_unique( $dependencies ) );
	}

	/**
	 * Register every module supplied by one provider.
	 *
	 * @param ModuleProvider $provider Module provider.
	 *
	 * @throws UnexpectedValueException When a provider yields a value that is not a module.
	 */
	public function register_provider( ModuleProvider $provider ): void {
		foreach ( $provider->modules() as $module ) {
			if ( ! $module instanceof Module ) {
				throw new UnexpectedValueException( 'Module providers must yield Module instances.' );
			}

			$this->register( $module );
		}
	}

	/**
	 * Boot every registered module once.
	 *
	 * Dependencies are always booted before the module that requires them.
	 *
	 * @throws RuntimeException When a dependency is missing or cyclic.
	 */
	public function boot(): void {
		foreach ( array_keys( $this->modules ) as $module_id ) {
			$this->boot_module( $module_id, array() );
		}
	}

	/**
	 * Return registered identifiers in registration order.
	 *
	 * @return list<string>
	 */
	public function registered_ids(): array {
		return array_keys( $this->modules );
	}

	/**
	 * Boot one module and its dependencies.
	 *
	 * @param string              $module_id Module identifier.
	 * @param array<string, true> $visiting  Modules in the current dependency path.
	 *
	 * @throws RuntimeException When a dependency is missing or cyclic.
	 */
	private function boot_module( string $module_id, array $visiting ): void {
		if ( isset( $this->booted[ $module_id ] ) ) {
			return;
		}

		if ( isset( $visiting[ $module_id ] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception text is never rendered.
			throw new RuntimeException( sprintf( 'Circular module dependency detected at "%s".', $module_id ) );
		}

		if ( ! isset( $this->modules[ $module_id ] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception text is never rendered.
			throw new RuntimeException( sprintf( 'Required module "%s" is not registered.', $module_id ) );
		}

		$visiting[ $module_id ] = true;
		$module                 = $this->modules[ $module_id ];

		foreach ( $this->dependencies[ $module_id ] as $dependency_id ) {
			$this->boot_module( $dependency_id, $visiting );
		}

		$module->boot();
		$this->booted[ $module_id ] = true;
	}

	/**
	 * Check whether an identifier is safe and stable.
	 *
	 * @param string $module_id Module or dependency identifier.
	 */
	private function is_valid_id( string $module_id ): bool {
		return 1 === preg_match( '/^[a-z][a-z0-9-]*$/', $module_id );
	}
}
