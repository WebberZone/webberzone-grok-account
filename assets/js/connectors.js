/**
 * Account sign-in card for Settings → Connectors.
 *
 * ES module, minified with pnpm run build:assets. React and components come from the wp-* classic
 * scripts that the Connectors page already loads; all strings are translated in PHP and passed
 * in through the module data.
 */
import {
	__experimentalRegisterConnector as registerConnector,
	__experimentalConnectorItem as ConnectorItem,
} from '@wordpress/connectors';

const MODULE = 'wzgka-connectors';

const {
	createElement: el,
	createInterpolateElement,
	useEffect,
	useRef,
	useState,
} = window.wp.element;
const {
	Button,
	ExternalLink,
	Modal,
	Notice,
	Spinner,
	__experimentalHStack: HStack,
	__experimentalVStack: VStack,
	__experimentalText: Text,
} = window.wp.components;

const dataElement = document.getElementById( 'wp-script-module-data-' + MODULE );
const config = dataElement ? JSON.parse( dataElement.textContent ) : {};
const s = config.strings || {};

function call( action ) {
	return window
		.fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: new URLSearchParams( { action: 'wzgka_' + action, _ajax_nonce: config.nonce } ),
		} )
		.then( ( response ) => response.json() )
		.then( ( result ) => {
			if ( ! result.success ) {
				const error = new Error( result.data?.message || result.data || s.requestFailed );
				error.retryable = result.data?.retryable === true;
				throw error;
			}
			return result.data;
		} );
}

function linked( text, url ) {
	if ( ! url ) {
		return text.replace( /<\/?a>/g, '' );
	}
	return createInterpolateElement( text, { a: el( ExternalLink, { href: url } ) } );
}

function Note() {
	return config.note ? el( Text, { as: 'p' }, linked( config.note, config.noteUrl ) ) : null;
}

function SignInModal( { onClose, onConnected } ) {
	const [ flow, setFlow ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ attempt, setAttempt ] = useState( 0 );
	const [ copied, setCopied ] = useState( false );
	const timer = useRef();
	const canCopy = typeof window.navigator.clipboard?.writeText === 'function';

	useEffect( () => {
		let active = true;
		setFlow( null );
		setError( null );

		const poll = ( interval, expiresAt, failures = 0 ) => {
			timer.current = window.setTimeout( () => {
				call( 'poll' )
					.then( ( status ) => {
						if ( ! active ) {
							return;
						}
						if ( 'connected' === status ) {
							onConnected();
							return;
						}
						poll( interval, expiresAt );
					} )
					.catch( ( e ) => {
						if ( ! active ) {
							return;
						}
						if ( e.retryable !== false && Date.now() < expiresAt * 1000 ) {
							poll( interval, expiresAt, Math.min( failures + 1, 3 ) );
							return;
						}
						setError( e.message );
					} );
			}, interval * 1000 * 2 ** failures );
		};

		call( 'start' )
			.then( ( data ) => {
				if ( active ) {
					setFlow( data );
					poll( data.interval, data.expires_at );
				}
			} )
			.catch( ( e ) => active && setError( e.message ) );

		return () => {
			active = false;
			window.clearTimeout( timer.current );
		};
	}, [ attempt ] );

	const close = () => {
		window.clearTimeout( timer.current );
		call( 'cancel' ).catch( () => {} );
		onClose();
	};

	const copy = () => {
		if ( ! canCopy ) {
			return;
		}
		window.navigator.clipboard
			.writeText( flow.user_code )
			.then( () => setCopied( true ) )
			.catch( () => {} );
	};

	let body;
	if ( error ) {
		body = el(
			VStack,
			{ spacing: 4 },
			el( Notice, { status: 'error', isDismissible: false }, error ),
			el( Note ),
			el(
				HStack,
				{ justify: 'flex-start' },
				el( Button, { variant: 'primary', onClick: () => setAttempt( attempt + 1 ) }, s.tryAgain ),
				el( Button, { variant: 'tertiary', onClick: close }, s.cancel )
			)
		);
	} else if ( ! flow ) {
		body = el( HStack, { justify: 'flex-start' }, el( Spinner ), el( Text, null, s.requesting ) );
	} else {
		body = el(
			VStack,
			{ spacing: 4 },
			el( Note ),
			el( Text, { as: 'p' }, linked( s.deviceStep, flow.verification_url ) ),
			el( Text, { as: 'p' }, s.enterCode ),
			el(
				HStack,
				{ justify: 'flex-start', spacing: 3 },
				el(
					'code',
					{ style: { fontSize: '1.75em', padding: '0.4em 0.6em', letterSpacing: '0.1em', userSelect: 'all' } },
					flow.user_code
				),
				el( Button, { variant: 'secondary', size: 'compact', onClick: copy, disabled: ! canCopy }, copied ? s.copied : s.copy )
			),
			el( HStack, { justify: 'flex-start' }, el( Spinner ), el( Text, { variant: 'muted' }, s.waiting ) )
		);
	}

	return el( Modal, { title: s.signIn, onRequestClose: close, size: 'medium' }, body );
}

function AccountConnector( { name, description, logo } ) {
	const [ isOpen, setIsOpen ] = useState( false );
	const [ isBusy, setIsBusy ] = useState( false );
	const [ error, setError ] = useState( null );
	const connected = !! config.connected;

	const disconnect = () => {
		setIsBusy( true );
		setError( null );
		call( 'disconnect' )
			.then( () => window.location.reload() )
			.catch( ( e ) => {
				setError( e.message );
				setIsBusy( false );
			} );
	};

	const actionArea = el(
		HStack,
		{ spacing: 3, expanded: false },
		connected &&
			el(
				Text,
				{ style: { color: 'var(--wp-components-color-accent-darker-10, #008a20)' } },
				s.connected
			),
		connected
			? el(
					Button,
					{ variant: 'tertiary', size: 'compact', isDestructive: true, isBusy, disabled: isBusy, onClick: disconnect },
					s.disconnect
			  )
			: el( Button, { variant: 'secondary', size: 'compact', onClick: () => setIsOpen( true ) }, s.signIn )
	);

	return el(
		ConnectorItem,
		{ className: 'connector-item--' + MODULE, logo, name, description, actionArea },
		connected && config.account ? el( Text, { variant: 'muted', size: 12 }, config.account ) : null,
		error && el( Notice, { status: 'error', isDismissible: false }, error ),
		isOpen &&
			el( SignInModal, {
				onClose: () => setIsOpen( false ),
				onConnected: () => window.location.reload(),
			} )
	);
}

if ( config.slug ) {
	registerConnector( config.slug, { render: AccountConnector } );
}
