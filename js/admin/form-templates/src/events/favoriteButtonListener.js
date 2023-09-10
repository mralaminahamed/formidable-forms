/**
 * Copyright (C) 2023 Formidable Forms
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

/**
 * Internal dependencies
 */
import { PREFIX, getAppState, setAppStateProperty } from '../shared';
import { templatesList, featuredTemplatesList, favoritesCategoryCountEl, customTemplatesTitle } from '../elements';
import { onClickPreventDefault, isFavoriteTemplate, isCustomTemplate, isFeaturedTemplate, isFavoritesCategory, hide } from '../utils';

const HEART_ICON_SELECTOR = '.frm-form-templates-item-favorite-button use';
const FILLED_HEART_ICON = '#frm_heart_solid_icon';
const LINEAR_HEART_ICON = '#frm_heart_icon';
const OPERATION = {
	ADD: 'add',
	REMOVE: 'remove'
};

/**
 * Manages event handling for favorite buttons.
 *
 * @since x.x
*/
function addFavoriteButtonEvents() {
	const favoriteButtons = document.querySelectorAll( `.${PREFIX}-favorite-button` );
	// Attach click event listeners to each favorite button.
	favoriteButtons.forEach( favoriteButton => onClickPreventDefault( favoriteButton, onFavoriteButtonClick ) );
}

/**
 * Handles the click event on the add to favorite button.
 *
 * @since x.x
 *
 * @param {Event} event The click event object.
 */
const onFavoriteButtonClick = ( event ) => {
	const favoriteButton = event.currentTarget;

	// Check if the button is currently disabled
	if ( 'true' === favoriteButton.getAttribute( 'data-disabled' ) ) {
		return;
	}

	// Temporarily disable the button to prevent multiple clicks
	favoriteButton.setAttribute( 'data-disabled', 'true' );

	/**
	 * Get necessary template information
	 */
	const template = favoriteButton.closest( `.${PREFIX}-item` );
	const templateId = template.dataset.id;
	const isFavorited = isFavoriteTemplate( template );
	const isTemplateCustom = isCustomTemplate( template );
	const isTemplateFeatured = isFeaturedTemplate( template );

	/**
	 * Toggle the favorite status in the UI.
	 * If template is featured, toggle its twin version in the respective list.
	 */
	let twinFeaturedTemplate = null;

	template.classList.toggle( `${PREFIX}-favorite-item`, ! isFavorited );
	if ( isTemplateFeatured ) {
		const templateList = template.closest( `#${PREFIX}-featured-list` ) ? featuredTemplatesList : templatesList;
		twinFeaturedTemplate = templateList?.querySelector( `.${PREFIX}-item[data-id="${templateId}"]` );

		// Toggle twin template's favorite status
		twinFeaturedTemplate?.classList.toggle( `${PREFIX}-item`, ! isFavorited );
	}

	/**
	 * Update favorite counts and icons based on the new state
	 */
	let { favoritesCount } = getAppState();
	const currentOperation = isFavorited ? OPERATION.REMOVE : OPERATION.ADD;
	const heartIcon = template.querySelector( HEART_ICON_SELECTOR );
	const twinTemplateHeartIcon = twinFeaturedTemplate?.querySelector( HEART_ICON_SELECTOR );

	if ( OPERATION.ADD === currentOperation ) {
		// Increment favorite counts
		++favoritesCount.total;
		isTemplateCustom ? ++favoritesCount.custom : ++favoritesCount.default;
		// Set heart icon to filled
		heartIcon.setAttribute( 'xlink:href', FILLED_HEART_ICON );
		twinTemplateHeartIcon?.setAttribute( 'xlink:href', FILLED_HEART_ICON );
	} else {
		// Decrement favorite counts
		--favoritesCount.total;
		isTemplateCustom ? --favoritesCount.custom : --favoritesCount.default;
		// Set heart icon to outline
		heartIcon.setAttribute( 'xlink:href', LINEAR_HEART_ICON );
		twinTemplateHeartIcon?.setAttribute( 'xlink:href', LINEAR_HEART_ICON );
	}

	// Update UI and state to reflect new favorite counts
	favoritesCategoryCountEl.textContent = favoritesCount.total;
	setAppStateProperty( 'favoritesCount', favoritesCount );

	/**
	 * Hide UI elements if 'Favorites' is active and counts are zero.
	 */
	if ( isFavoritesCategory( selectedCategory ) ) {
		hide( template );

		if ( 0 === favoritesCount.default ) {
			hide( templatesList );
		}

		if ( 0 === favoritesCount.custom || 0 === favoritesCount.default ) {
			hide( customTemplatesTitle );
		}
	}

	/**
	 * Update server-side data for favorite templates
	 */
	const formData = new FormData();
	formData.append( 'template_id', template.dataset.id );
	formData.append( 'operation', currentOperation );
	formData.append( 'is_custom_template', isTemplateCustom );

	doJsonPost( 'add_or_remove_favorite_template', formData )
		.finally( () => {
			// Finally, re-enable the button
			favoriteButton.setAttribute( 'data-disabled', 'false' );
		});
};

export default addFavoriteButtonEvents;
