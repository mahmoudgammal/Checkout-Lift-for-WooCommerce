/**
 * Pure operations on the editor's field list.
 *
 * Kept out of the component so they can be tested without rendering, and so
 * the rules about what happens to a field when it moves live in one place.
 *
 * @package SimpleCheckoutFieldsEditor
 */

/**
 * Move a field within its section.
 *
 * Order is array order, which is exactly what the server stores, so moving a
 * row is the whole of reordering.
 *
 * @param {Array}  sections   Current sections.
 * @param {string} sectionKey Section being reordered.
 * @param {number} from       Current index.
 * @param {number} to         Target index.
 * @return {Array} Sections, unchanged when the move is impossible.
 */
export function moveField( sections, sectionKey, from, to ) {
	if ( from === to || to < 0 ) {
		return sections;
	}

	return sections.map( ( section ) => {
		if ( section.key !== sectionKey || to >= section.fields.length || from < 0 || from >= section.fields.length ) {
			return section;
		}

		const fields = [ ...section.fields ];
		const [ moved ] = fields.splice( from, 1 );
		fields.splice( to, 0, moved );

		return { ...section, fields };
	} );
}

/**
 * Add a field, or replace it in place.
 *
 * A field whose section changed is removed from the old one before being
 * appended to the new, so moving it cannot leave a copy behind.
 *
 * @param {Array}   sections Current sections.
 * @param {Object}  field    Field as edited.
 * @param {string}  target   Section it should end up in.
 * @param {?string} original Section it came from, or null when adding.
 * @return {Array} Sections.
 */
export function upsertField( sections, field, target, original = null ) {
	return sections.map( ( section ) => {
		let fields = section.fields;

		if ( original && section.key === original && original !== target ) {
			fields = fields.filter( ( f ) => f.key !== field.key );
		}

		if ( section.key === target ) {
			const existing = fields.findIndex( ( f ) => f.key === field.key );

			fields =
				existing === -1
					? [ ...fields, field ]
					: fields.map( ( f, i ) => ( i === existing ? field : f ) );
		}

		return fields === section.fields ? section : { ...section, fields };
	} );
}

/**
 * Remove a field from a section.
 *
 * @param {Array}  sections   Current sections.
 * @param {string} sectionKey Section the field is in.
 * @param {string} fieldKey   Field to remove.
 * @return {Array} Sections.
 */
export function removeField( sections, sectionKey, fieldKey ) {
	return sections.map( ( section ) =>
		section.key !== sectionKey
			? section
			: { ...section, fields: section.fields.filter( ( f ) => f.key !== fieldKey ) }
	);
}

/**
 * Apply a change to one field.
 *
 * @param {Array}  sections   Current sections.
 * @param {string} sectionKey Section the field is in.
 * @param {string} fieldKey   Field to change.
 * @param {Object} changes    Properties to merge.
 * @return {Array} Sections.
 */
export function updateField( sections, sectionKey, fieldKey, changes ) {
	return sections.map( ( section ) =>
		section.key !== sectionKey
			? section
			: {
					...section,
					fields: section.fields.map( ( field ) =>
						field.key === fieldKey ? { ...field, ...changes } : field
					),
			  }
	);
}
