<?php
/**
 * Custom table lifecycle module.
 *
 * @package SimpleCheckoutFieldsEditor
 */

declare( strict_types=1 );

namespace DesignTich\SimpleCheckoutFieldsEditor\Core\Database;

use DesignTich\SimpleCheckoutFieldsEditor\Core\Contracts\Module;

/**
 * Keeps the custom tables in step with the schema this build expects.
 */
final class DatabaseModule implements Module {

	/**
	 * Create the database module.
	 *
	 * @param Migrator $migrator Schema migrator.
	 */
	public function __construct( private Migrator $migrator ) {
	}

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'database';
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
		// Run schema changes only on admin requests; frontend checks read the cached version option.
		if ( ! $this->migrator->needs_migration() ) {
			return;
		}

		add_action( 'admin_init', array( $this, 'run_migration' ) );
	}

	/**
	 * Run the migration, discarding its result.
	 *
	 * An action callback must not return a value, while `migrate()` reports
	 * whether it did any work for callers that care.
	 */
	public function run_migration(): void {
		$this->migrator->migrate();
	}
}
