( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		var button = document.getElementById( 'telos-gym-schedule-test-connection' );
		var result = document.getElementById( 'telos-gym-schedule-test-result' );
		var field = document.getElementById( 'telos_gym_schedule_embed_key' );
		if ( ! button || ! result || ! field || typeof telosGymScheduleAdmin === 'undefined' ) {
			return;
		}

		function setResult( text, cls ) {
			result.textContent = text;
			result.className = 'telos-gym-schedule-test-result' + ( cls ? ' ' + cls : '' );
		}

		function testKey( key ) {
			var url = telosGymScheduleAdmin.apiBase.replace( /\/$/, '' ) + '/api/public/v1/schedule/?days=1';
			fetch( url, { headers: { 'X-Telos-Embed-Key': key } } )
				.then( function ( response ) {
					if ( response.ok ) {
						setResult( telosGymScheduleAdmin.strings.ok, 'is-ok' );
					} else {
						setResult( telosGymScheduleAdmin.strings.fail, 'is-fail' );
					}
				} )
				.catch( function () {
					setResult( telosGymScheduleAdmin.strings.fail, 'is-fail' );
				} );
		}

		button.addEventListener( 'click', function () {
			var typed = field.value.trim();
			setResult( telosGymScheduleAdmin.strings.testing, '' );

			if ( typed ) {
				testKey( typed );
				return;
			}

			var body = new URLSearchParams();
			body.set( 'action', 'telos_gym_schedule_get_key_for_test' );
			body.set( 'nonce', telosGymScheduleAdmin.nonce );

			fetch( telosGymScheduleAdmin.ajaxUrl, { method: 'POST', body: body } )
				.then( function ( response ) { return response.json(); } )
				.then( function ( json ) {
					if ( json.success && json.data && json.data.key ) {
						testKey( json.data.key );
					} else {
						setResult( telosGymScheduleAdmin.strings.noKey, 'is-fail' );
					}
				} )
				.catch( function () {
					setResult( telosGymScheduleAdmin.strings.fail, 'is-fail' );
				} );
		} );

		if ( document.querySelector( '.telos-gym-schedule-color-picker' ) && window.jQuery && window.jQuery.fn.wpColorPicker ) {
			window.jQuery( '.telos-gym-schedule-color-picker' ).wpColorPicker();
		}
	} );
} )();
