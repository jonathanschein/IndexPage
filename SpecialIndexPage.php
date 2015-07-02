<?php

/**
 * Implements Special:IndexPage
 *
 * @ingroup SpecialPage
 */
class SpecialIndexPage extends SpecialAllPages {

	/**
	 * Whether to remove the searched prefix from the displayed link. Useful
	 * for inclusion of a set of sub pages in a root page.
	 */
	protected $stripPrefix = false;

	protected $hideRedirects = false;
        
        protected $maxPerPage = 1000;

	// number of columns in output table
	protected $columns = 1;

	// Inherit $maxPerPage

	function __construct() {
		parent::__construct( 'IndexPage' );
	}

	function execute( $par ) {
		global $wgContLang;

		$this->setHeaders();
		
		$out = $this->getOutput();
		$out->addModuleStyles( 'mediawiki.special' );

		# GET values
		$request = $this->getRequest();
		$from = '';
                $prefix = $request->getVal( 'prefix', '' );
                $namespace = 0;
		$namespaces = $wgContLang->getNamespaces();
		$out->setPageTitle(
			( $namespace > 0 && array_key_exists( $namespace, $namespaces ) )
				? $this->msg( 'prefixindex-namespace', str_replace( '_', ' ', $namespaces[$namespace] ) )
				: $this->msg( 'indexpage'.$prefix)
		);

		$showme = '';
		if ( $par !== null ) {
			$showme = $par;
		} elseif ( $prefix != '' ) {
			$showme = $prefix;
		} elseif ( $from != '' && $ns === null ) {
			// For back-compat with Special:Allpages
			// Don't do this if namespace is passed, so paging works when doing NS views.
			$showme = $from;
		}

		// Bug 27864: if transcluded, show all pages instead of the form.
		if ( $this->including() || $showme != '' || $ns !== null ) {
			$this->showPrefixChunk( $namespace, $showme, $from );
		} else {
			//$out->addHTML( $this->namespacePrefixForm( $namespace, null ) );
		}
	}

	/**
	 * @param int $namespace Default NS_MAIN
	 * @param string $prefix
	 * @param string $from List all pages from this name (default false)
	 */
	protected function showPrefixChunk( $namespace = NS_MAIN, $prefix) {
		global $wgContLang;

		$prefixList = $this->getNamespaceKeyAndText( $namespace, $prefix );
		$namespaces = $wgContLang->getNamespaces();
		$res = null;
                
		if ( !$prefixList ) {
			$out = $this->msg( 'allpagesbadtitle' )->parseAsBlock();
		} elseif ( !array_key_exists( $namespace, $namespaces ) ) {
			// Show errormessage and reset to NS_MAIN
			$out = $this->msg( 'allpages-bad-ns', $namespace )->parse();
			$namespace = NS_MAIN;
		} else {
			list( $namespace, $prefixKey, $prefix ) = $prefixList;
			### @todo FIXME: Should complain if $fromNs != $namespace

			$dbr = wfGetDB( DB_SLAVE );

			$conds = array(
				'page_namespace' => $namespace,
				'page_title' . $dbr->buildLike( $prefixKey, $dbr->anyString() ),
				'page_title >= ' . $dbr->addQuotes( $prefixKey ),
			);

			if ( $this->hideRedirects ) {
				$conds['page_is_redirect'] = 0;
			}

			$res = $dbr->select( 'page',
				array( 'page_namespace', 'page_title', 'page_is_redirect' ),
				$conds,
				__METHOD__,
				array(
					'ORDER BY' => 'page_title',
					'LIMIT' => $this->maxPerPage + 1,
					'USE INDEX' => 'name_title',
				)
			);

			### @todo FIXME: Side link to previous

			$n = 0;
			if ( $res->numRows() > 0 ) {
				$out = Xml::openElement( 'table', array( 'class' => '' ) );

				$prefixLength = strlen( $prefix );
				while ( ( $n < $this->maxPerPage ) && ( $s = $res->fetchObject() ) ) {
					$t = Title::makeTitle( $s->page_namespace, $s->page_title );
					if ( $t ) {
						$displayed = $t->getText();
						// Try not to generate unclickable links
						if ( $this->stripPrefix && $prefixLength !== strlen( $displayed ) ) {
							$displayed = substr( $displayed, $prefixLength );
						}
						$link = ( $s->page_is_redirect ? '<div class="allpagesredirect">' : '' ) .
							Linker::linkKnown(
								$t,
								htmlspecialchars( $displayed ),
								$s->page_is_redirect ? array( 'class' => 'mw-redirect' ) : array()
							) .
							( $s->page_is_redirect ? '</div>' : '' );
					} else {
						$link = '[[' . htmlspecialchars( $s->page_title ) . ']]';
					}
					if ( $n % $this->columns == 0 ) {
						$out .= '<tr>';
					}
					$out .= "<td>$link</td>";
					$n++;
					if ( $n % $this->columns == 0 ) {
						$out .= '</tr>';
					}
				}

				if ( $n % $this->columns != 0 ) {
					$out .= '</tr>';
				}

				$out .= Xml::closeElement( 'table' );
			} else {
				$out = '';
			}
		}

		$footer = '';
                $title = $prefixKey;
		if ( $this->including() ) {
			$out2 = '';
		} else {
			//$nsForm = $this->namespacePrefixForm( $namespace, $prefix );
			$self = $this->getPageTitle();
			$out2 = '';
                                //Xml::openElement( 'table', array( 'id' => 'mw-prefixindex-nav-table' ) ) .
				//'<tr>
				//	<td>' .
				//$nsForm .
				//'</td>
				//<td id="mw-prefixindex-nav-form" class="mw-prefixindex-nav">';

			if ( $res && ( $n == $this->maxPerPage ) && ( $s = $res->fetchObject() ) ) {
				$query = array(
					'from' => $s->page_title,
					'prefix' => $prefix,
					'hideredirects' => $this->hideRedirects,
					'stripprefix' => $this->stripPrefix,
					'columns' => $this->columns,
				);

				if ( $namespace || $prefix == '' ) {
					// Keep the namespace even if it's 0 for empty prefixes.
					// This tells us we're not just a holdover from old links.
					$query['namespace'] = $namespace;
				}

				$nextLink = Linker::linkKnown(
					$self,
					$this->msg( 'nextpage', str_replace( '_', ' ', $s->page_title ) )->escaped(),
					array(),
					$query
				);

				$out2 .= $nextLink;

				$footer = "\n" . Html::element( 'hr' ) .
					Html::rawElement(
						'div',
						array( 'class' => 'mw-prefixindex-nav' ),
						$nextLink
					);
			}
			$out2 .= "</td></tr>" .
				Xml::closeElement( 'table' );
		}

		$this->getOutput()->addHTML( $title.$out2 . $out . $footer );
	}

	protected function getGroupName() {
		return 'pages';
	}
}
