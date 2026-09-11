import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';
import './editor.scss';

registerBlockType( metadata.name, {
	edit: function Edit( { attributes, setAttributes } ) {
		var days = attributes.days;
		var accentColor = attributes.accentColor;
		var blockProps = useBlockProps();

		return (
			<>
				<InspectorControls>
					<PanelBody title={ __( 'Schedule settings', 'telos-gym-schedule' ) }>
						<RangeControl
							label={ __( 'Days to show', 'telos-gym-schedule' ) }
							value={ days }
							onChange={ function ( value ) { setAttributes( { days: value } ); } }
							min={ 1 }
							max={ 30 }
						/>
						<TextControl
							label={ __( 'Accent colour override', 'telos-gym-schedule' ) }
							help={ __( 'Leave blank to use the plugin-wide colour from Settings.', 'telos-gym-schedule' ) }
							value={ accentColor }
							onChange={ function ( value ) { setAttributes( { accentColor: value } ); } }
						/>
					</PanelBody>
				</InspectorControls>
				<div { ...blockProps }>
					<div className="telos-gym-schedule-placeholder">
						<strong>{ __( 'TelosGym Schedule', 'telos-gym-schedule' ) }</strong>
						<p>{ __( 'Live schedule will appear here.', 'telos-gym-schedule' ) }</p>
					</div>
				</div>
			</>
		);
	},
	save: function () {
		return null;
	},
} );
