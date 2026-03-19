const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config' );

module.exports = {
	...defaultConfig,
	roots: [ '<rootDir>/wp-content/plugins/wordpress-groups/blocks' ],
	testMatch: [ '**/?(*.)test.[jt]s?(x)' ],
};
