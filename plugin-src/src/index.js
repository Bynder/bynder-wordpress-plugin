// Import Compact View
import { CompactView, Modal, Login } from '@bynder/compact-view';

// Import CSS
import './editor.scss';
import './style.scss';

// Import WordPress packages
import { createBlock, registerBlockType } from '@wordpress/blocks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { dispatch, select } from '@wordpress/data';
import { cloneElement, createElement, Fragment } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { replaceInnerBlocks } from '@wordpress/block-editor'

const bynderLogo = (props) => (
	<svg width={24} height={24} viewBox="0 0 20 20" {...props}>
		<g fill="none" fillRule="evenodd">
			<rect width={20} height={20} fill="#0AF" rx={4} />
			<path
				fill="#FFF"
				fillRule="nonzero"
				d="M13.65 4.752c-.958-.001-1.87.412-2.5 1.133L6.69 10.338 5.416 9.062a1.315 1.315 0 01-.336-.895 1.34 1.34 0 011.349-1.341c.326-.004.642.116.885.334.08.07.305.304.305.304l1.412-1.412-.408-.408a3.295 3.295 0 00-2.275-.892 3.323 3.323 0 00-3.327 3.32c0 .807.29 1.587.818 2.197L6.7 13.144l5.982-5.982c.244-.22.562-.34.89-.336a1.34 1.34 0 011.341 1.351c.003.327-.117.644-.335.888l-3.976 3.975a.836.836 0 01-1.203-.01l-.3-.296-1.405 1.405.288.283c.531.544 1.26.85 2.02.85h.001c.757.001 1.482-.305 2.009-.848l4.143-4.152a3.332 3.332 0 00-2.506-5.52z"
			/>
		</g>
	</svg>
);

const assetTypes = ['IMAGE', 'VIDEO', 'DOCUMENT', 'AUDIO'];
const assetFieldSelection = `
  databaseId
  name
  type
  files
  ... on Video {
    previewUrls
  }
`;

// Set hook to blocks.registerBlockType to add bynder related attributes to core/image, core/video and core/gallery blocks
addFilter('blocks.registerBlockType', 'bynderAttributes', (settings) => {
	// Check if object exists for old Gutenberg version compatibility
	if (typeof settings.attributes !== 'undefined') {
		if (['core/image', 'core/video', 'core/audio'].includes(settings.name)) {
			settings.attributes = Object.assign(settings.attributes, {
				bynder: {
					type: 'string',
					source: 'attribute',
					selector: 'figure',
					attribute: 'data-bynder-id',
					default: '',
				},
			});
		}
		if (settings.name == 'core/file') {
			settings.attributes = Object.assign(settings.attributes, {
				bynder: {
					type: 'string',
					source: 'attribute',
					selector: 'div',
					attribute: 'data-bynder-id',
					default: '',
				},
			});
		}
		if (settings.name === 'core/gallery') {
			settings.attributes = Object.assign(settings.attributes, {
				bynderGallery: {
					type: 'boolean',
					default: false,
				},
				isOpen: {
					type: 'boolean',
					default: false,
				},
			});
			settings.attributes.images.query = Object.assign(
				settings.attributes.images.query,
				{
					bynder: {
						type: 'string',
						source: 'attribute',
						selector: 'figure',
						attribute: 'data-bynder-id',
						default: '',
					},
				}
			);
		}
	}
	return settings;
});

// Set hook to blocks.getSaveElement to insert bynder assets ids to figure tags
addFilter(
	'blocks.getSaveElement',
	'bynderIds',
	(element, block, attributes) => {

		// Add bynder id to images and videos
		if (
			['core/image', 'core/video', 'core/file', 'core/audio'].includes(block.name) &&
			attributes.bynder
		) {
			return cloneElement(element, {
				'data-bynder-id': attributes.bynder,
			});
		}
		return element;
	}
);

// Set hook to editor.BlockEdit to not render gallery default buttons for bynder gallery and handle compact view to add more images to gallery
addFilter(
	'editor.BlockEdit',
	'bynderGallery',
	createHigherOrderComponent((BlockEdit) => (props) => {
		var attributes = props.attributes;
		if (props.name === 'core/gallery' && attributes.bynderGallery) {
			var openModal = () => {
				props.setAttributes({
					isOpen: true,
				});
			};

			var closeModal = () => {
				props.setAttributes({
					isOpen: false,
				});
			};

			var addToGallery = (assets) => {
				var galleryImages = assets.reduce((result, asset) => {
					if(asset.type === "IMAGE") {
						var file =
							asset.files[cgbGlobal.bynderImageDerivative] ||
							asset.files.webImage;
						result.push(createBlock('core/image', {
							url: file.url,
							alt: asset.name,
							bynder: asset.databaseId,
						}));
					}
					return result;
				}, []);
				const parentBlock = select('core/block-editor').getBlocksByClientId(props.clientId)[0];
				const existingGalleryImages = parentBlock.innerBlocks;
				dispatch('core/block-editor').replaceInnerBlocks(
					props.clientId,
					 [...existingGalleryImages, ...galleryImages],

				);
				closeModal();
			};

			return (
				<Fragment>
					<div className="bynder-gallery">
						<BlockEdit {...props} />
						<div className="compact-view-button">
							<React.Fragment>
								<button
									onClick={openModal}
									className="components-button button button-large"
								>
									Open Compact View
								</button>

								<Modal
									isOpen={attributes.isOpen}
									onClose={closeModal}
								>
									<Login
										portal={{
											url: cgbGlobal.bynderDomain,
											editable: false,
										}}
									>
										<CompactView
											language={cgbGlobal.language}
											assetTypes={['IMAGE']}
											assetFieldSelection={
												assetFieldSelection
											}
											onSuccess={addToGallery}
											defaultSearchTerm={
												cgbGlobal.bynderDefaultSearchTerm
											}
										/>
									</Login>
								</Modal>
							</React.Fragment>
						</div>
					</div>
				</Fragment>
			);
		}
		return <BlockEdit {...props} />;
	})
);

/**
 * Register a Gutenberg Block for Bynder Asset
 *
 * Registers a new block provided a unique name and an object defining its
 * behavior. Once registered, the block is made editor as an option to any
 * editor interface where blocks are implemented.
 *
 * @link https://wordpress.org/gutenberg/handbook/block-api/
 * @param  {string}   name     Block name.
 * @param  {Object}   settings Block settings.
 * @return {?WPBlock}          The block, if it has been successfully
 *                             registered; otherwise `undefined`.
 */
registerBlockType('bynder/bynder-asset-block', {
	title: 'Bynder Asset',
	icon: bynderLogo,
	category: 'common',
	attributes: {
		isOpen: {
			type: 'boolean',
			default: false,
		},
	},
	/**
	 * The edit function describes the structure of your block in the context of the editor.
	 * This represents what the editor will render when the block is used.
	 *
	 * The "edit" property must be a valid function.
	 *
	 * @link https://wordpress.org/gutenberg/handbook/block-api/block-edit-save/
	 *
	 * @param {Object} props Props.
	 * @returns {Mixed} JSX Component.
	 */
	edit: (props) => {
		var attributes = props.attributes;

		var openModal = () => {
			props.setAttributes({
				isOpen: true,
			});
		};

		var closeModal = () => {
			props.setAttributes({
				isOpen: false,
			});
		};

		var addAsset = (assets, additionalInfo) => {
			const asset = assets[0];
			var block;
			switch (asset.type) {
				case 'IMAGE':
					var file =
						asset.files[cgbGlobal.bynderImageDerivative] ||
						asset.files.webImage;

					if(cgbGlobal.bynderSelectionMode === "SingleSelectFile" && additionalInfo.selectedFile) {
						file = additionalInfo.selectedFile || file
					}
					block = createBlock('core/image', {
						// Fetching the webimage derivative by default
						url: file.url,
						alt: asset.name,
						bynder: asset.databaseId,
					});
					break;
				case 'VIDEO':
					// Fetching the mp4 video preview url by default, fallback to original if mp4 isn't found
					var url = asset.previewUrls.find((previewUrl) => {
						var extension = previewUrl.split('.').pop();
						return extension === 'mp4';
					});
					var videoUrl = url ? url : asset.files.original.url;
					block = createBlock('core/video', {
						src: videoUrl,
						bynder: asset.databaseId,
					});
					break;
				case 'AUDIO':
					var audioUrl = asset.files.original.url;
					block = createBlock('core/audio', {
						src: audioUrl,
						bynder: asset.databaseId,
					});
					break;
				case 'DOCUMENT':
					if (asset.files.original === undefined) {
						alert(
							asset.name +
								' is not marked as public and cannot be selected.'
						);
						break;
					}
					block = createBlock('core/file', {
						href: asset.files.original.url,
						fileName: asset.name,
						bynder: asset.databaseId,
					});
					break;
			}
			if (block !== undefined) {
				dispatch('core/block-editor').replaceBlock(
					props.clientId,
					block
				);
				closeModal();
			}
		};

		return (
			<React.Fragment>
				<button
					onClick={openModal}
					className="components-button button button-large"
				>
					Open Compact View
				</button>

				<Modal isOpen={attributes.isOpen} onClose={closeModal}>
					<Login
						portal={{
							url: cgbGlobal.bynderDomain,
							editable: false,
						}}
					>
						<CompactView
							language={cgbGlobal.language}
							mode={cgbGlobal.bynderSelectionMode}
							assetTypes={assetTypes}
							assetFieldSelection={assetFieldSelection}
							onSuccess={addAsset}
							defaultSearchTerm={
								cgbGlobal.bynderDefaultSearchTerm
							}
						/>
					</Login>
				</Modal>
			</React.Fragment>
		);
	},

	/**
	 * The save function defines the way in which the different attributes should be combined
	 * into the final markup, which is then serialized by Gutenberg into post_content.
	 *
	 * The "save" property must be specified and must be a valid function.
	 *
	 * @link https://wordpress.org/gutenberg/handbook/block-api/block-edit-save/
	 *
	 * @param {Object} props Props.
	 */
	save: (props) => {},
});

/**
 * Register a Gutenberg Block for Bynder Gallery
 *
 * Registers a new block provided a unique name and an object defining its
 * behavior. Once registered, the block is made editor as an option to any
 * editor interface where blocks are implemented.
 *
 * @link https://wordpress.org/gutenberg/handbook/block-api/
 * @param  {string}   name     Block name.
 * @param  {Object}   settings Block settings.
 * @return {?WPBlock}          The block, if it has been successfully
 *                             registered; otherwise `undefined`.
 */
registerBlockType('bynder/bynder-gallery-block', {
	title: 'Bynder Gallery',
	icon: bynderLogo,
	category: 'common',
	attributes: {
		isOpen: {
			type: 'boolean',
			default: false,
		},
	},
	/**
	 * The edit function describes the structure of your block in the context of the editor.
	 * This represents what the editor will render when the block is used.
	 *
	 * The "edit" property must be a valid function.
	 *
	 * @link https://wordpress.org/gutenberg/handbook/block-api/block-edit-save/
	 *
	 * @param {Object} props Props.
	 * @returns {Mixed} JSX Component.
	 */
	edit: (props) => {
		var attributes = props.attributes;

		var openModal = () => {
			props.setAttributes({
				isOpen: true,
			});
		};

		var closeModal = () => {
			props.setAttributes({
				isOpen: false,
			});
		};

		var addGallery = (assets) => {
			var galleryImages = assets.reduce((result, asset) => {
				if(asset.type === "IMAGE") {
					var file =
						asset.files[cgbGlobal.bynderImageDerivative] ||
						asset.files.webImage;
					result.push(createBlock('core/image', {
						url: file.url,
						alt: asset.name,
						bynder: asset.databaseId,
					}));
				}
				return result;
			}, []);
			var block = createBlock('core/gallery', {
				bynderGallery: true
			}, galleryImages);
			dispatch('core/block-editor').replaceBlock(props.clientId, block);
			closeModal();
		};

		return (
			<React.Fragment>
				<button
					onClick={openModal}
					className="components-button button button-large"
				>
					Open Compact View
				</button>

				<Modal isOpen={attributes.isOpen} onClose={closeModal}>
					<Login
						portal={{
							url: cgbGlobal.bynderDomain,
							editable: false,
						}}
					>
						<CompactView
							language={cgbGlobal.language}
							assetTypes={['IMAGE']}
							assetFieldSelection={assetFieldSelection}
							onSuccess={addGallery}
							defaultSearchTerm={
								cgbGlobal.bynderDefaultSearchTerm
							}
						/>
					</Login>
				</Modal>
			</React.Fragment>
		);
	},

	/**
	 * The save function defines the way in which the different attributes should be combined
	 * into the final markup, which is then serialized by Gutenberg into post_content.
	 *
	 * The "save" property must be specified and must be a valid function.
	 *
	 * @link https://wordpress.org/gutenberg/handbook/block-api/block-edit-save/
	 *
	 * @param {Object} props Props.
	 */
	save: (props) => {},
});

/**
 * Featured Image tab for Media Frame modal
 */
var l10n = wp.media.view.l10n;
wp.media.view.MediaFrame.Select.prototype.browseRouter = function( routerView ) {
	if(wp.media.frame && wp.media.frame.options.state === "featured-image") {
		routerView.set({
			upload: {
				text:     l10n.uploadFilesTitle,
				priority: 20
			},
			browse: {
				text:     l10n.mediaLibraryTitle,
				priority: 40
			},
			bynder: {
				text:     "Bynder",
				priority: 60
			}
		});
	} else {
		routerView.set({
			upload: {
				text:     l10n.uploadFilesTitle,
				priority: 20
			},
			browse: {
				text:     l10n.mediaLibraryTitle,
				priority: 40
			}
		});
	}
};

class UCVSingleSelect extends React.Component {
	constructor(props) {
		super(props);
		this.state = {
			isOpen: false,
			importState: "",
			msgContent: "",
			featuredImageUrl: ""
		};
	}
	render() {
		var openModal = () => {
			this.setState({isOpen: true});
		};

		var closeModal = () => {
			this.setState({isOpen: false});
		};

		var updateStateCallback = (state) => {
			this.setState(state);
		};


		var addAsset = (assets, additionalInfo) => {
			const asset = assets[0];
			var file =
					asset.files[cgbGlobal.bynderImageDerivative] ||
					asset.files.webImage;

			if(cgbGlobal.bynderSelectionMode === "SingleSelectFile" && additionalInfo.selectedFile) {
				file = additionalInfo.selectedFile || file
			}
			closeModal();

			// Set loading state
			this.setState({
				importState: "loading",
				msgContent: "Loading asset into Wordpress.."
			});

			const baseErrorMessage = "An error occurred while setting the featured image";
			wp.ajax.post( "bynder_featured", {
				'id': document.getElementById('post_ID').value,
				'url': file.url,
				'bynder-nonce': cgbGlobal.bynderNonce
			}).done(function(response){
    			if(response.att_id && response.url) {
					updateStateCallback({importState: "success", featuredImageUrl: response.url});
					var selection = wp.media.frame.state().get( 'selection' );
					selection.reset([ wp.media.attachment( response.att_id )]);
				} else {
					updateStateCallback({
						importState: "error",
						msgContent: baseErrorMessage
					});
				}
  			}).fail(function(response){
				// Error state if the download to the media library fails from Bynder
				const errorMsg = `: ${response.error} (${response.error_code})`;
				updateStateCallback({
					importState: "error",
					msgContent: baseErrorMessage + errorMsg
				});
			});
		};

		return (
			<React.Fragment>
				<div id="bynder-featured-image-preview">
					{(this.state.importState === "loading" || this.state.importState === "error") && (
						<div className="ucv-media-frame-message">{this.state.msgContent}</div>
					)}
					{this.state.importState === "success" && (
						<img src={this.state.featuredImageUrl} height="250"/>
					)}
				</div>
				<div>
					<button
						onClick={openModal}
						className="components-button button button-large"
					>
						Open Compact View
					</button>
				</div>
				<Modal isOpen={this.state.isOpen} onClose={closeModal}>
					<Login
						portal={{
							url: cgbGlobal.bynderDomain,
							editable: false,
						}}
					>
						<CompactView
							language={cgbGlobal.language}
							mode={cgbGlobal.bynderSelectionMode}
							assetTypes={["IMAGE"]}
							assetFieldSelection={assetFieldSelection}
							onSuccess={addAsset}
							defaultSearchTerm={
								cgbGlobal.bynderDefaultSearchTerm
							}
						/>
					</Login>
				</Modal>
			</React.Fragment>
		);
	}
}

document.addEventListener('click',function(e){
    if(e.target && e.target.id== 'menu-item-bynder'){
		renderCompactViewFeaturedImage();
     }
});
wp.media.view.Modal.prototype.on( "open", function(data) {
	renderCompactViewFeaturedImage();
});
function renderCompactViewFeaturedImage() {
	var mediaModals = document.querySelectorAll('.media-modal');
	mediaModals.forEach(function(mediaModal){
		var modal = mediaModal.parentElement;
		if(window.getComputedStyle(modal).display !== "none"
			&& modal.querySelector('.media-modal-content .media-router .media-menu-item.active#menu-item-bynder')) {
				const domContainer = modal.querySelector('body .media-modal-content .media-frame-content');
				domContainer.innerHTML = "";
				var ucvContainer = document.createElement('div');
				ucvContainer.setAttribute('class', 'ucv-media-frame');
				domContainer.appendChild(ucvContainer);
				ReactDOM.render(<UCVSingleSelect/>, ucvContainer);
		}
	});

}
