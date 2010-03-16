<?php
/**
 * Wrapper base for views in the database
 *
 * @package Core
 * @version $Revision: 94 $
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt LGPL
 */
class sfCouchView
{
	/*
	 * The ID of the design-view
	 */
	const viewName = '_design/sfCouch';

    /**
     * Build view query string from options
     *
     * Validates and transformed paased options to limit the view data, to fit
     * the specifications in the HTTP view API, documented at:
     * http://www.couchdbwiki.com/index.php?title=HTTP_View_API#Querying_Options
     *
     * @param array $options
     * @return string
     */
    private static function buildViewQuery( array $options )
    {
        // Return empty query string, if no options has been passed
        if ( $options === array() )
        {
            return '';
        }

        $queryString = '?';
        foreach ( $options as $key => $value )
        {
            switch ( $key )
            {
                case 'key':
                case 'startkey':
                case 'endkey':
                    // These values has to be valid JSON encoded strings, so we
                    // just encode the passed data, whatever it is, as CouchDB
                    // may use complex datatypes as a key, like arrays or
                    // objects.
                    $queryString .= $key . '=' . urlencode( json_encode( $value ) );
                    break;

                case 'startkey_docid':
                    // The docidstartkey is handled differntly then the other
                    // keys and is just passed as a string, because it always
                    // is and can only be a string.
                    $queryString .= $key . '=' . urlencode( (string) $value );
                    break;

                case 'group':
                case 'update':
                case 'descending':
                    // These two values may only contain boolean values, passed
                    // as "true" or "false". We just perform a typical PHP
                    // boolean typecast to transform the values.
                    $queryString .= $key . '=' . ( $value ? 'true' : 'false' );
                    break;

                case 'skip':
                case 'group_level':
                    // Theses options accept integers defining the limits of
                    // the query. We try to typecast to int.
                    $queryString .= $key . '=' . ( (int) $value );
                    break;

                case 'count': // CouchDB 0.8. compat
                case 'limit':
                    // Theses options accept integers defining the limits of
                    // the query. We try to typecast to int.
                    $queryString .= 'limit=' . ( (int) $value );
                    break;

                default:
                    throw new sfException( $key );
            }

            $queryString .= '&';
        }

        // Return query string, but remove appended '&' first.
        return substr( $queryString, 0, -1 );
    }

    /**
     * Query a view
     *
     * Query the specified view to get a set of results. You may optionally use
     * the view query options as additional paramters to limit the returns
     * values, specified at:
     * http://www.couchdbwiki.com/index.php?title=HTTP_View_API#Querying_Options
     *
     * @param string $view
     * @param array $options
     * @return sfCouchResultArray
     */
    public static function query( $view, array $options = array() )
    {
    	$response = null;
    	
        // Build query string, just as a normal HTTP GET query string
        $url = self::viewName . '/_view/' . $view;
        $url .= self::buildViewQuery( $options );

        // Get database connection, because we directly execute a query here.
        $db = sfCouchConnection::getInstance();

        // Always refresh the configuration in debug mode
        if(sfConfig::get('sf_debug')) {
        	self::refreshDesignDoc();
        }
        
        try
        {
            // Try to execute query, a failure most probably means, the view
            // has not been added, yet.
            $response = $db->get( $url );
        }
        catch ( sfException $e )
        {
            // If we aren't in debug mode Ensure view has been created properly and then try to execute
            // the query again. If it still fails, there is most probably a
            // real problem.
            if (!sfConfig::get('sf_debug') && self::refreshDesignDoc()) {
            	$response = $db->get($url);
            }
        }

        return $response;
    }


    /**
     * Create the view document
     *
     * Check if the views stored in the database equal the view definitions
     * specified by the vew classes. If the implmentation differs update to the
     * view specifications in the class.
     *
     * @return void
     */
    public static function refreshDesignDoc()
    {
    	$designDoc = new sfCouchDocument(self::viewName);
    	
    	$designDoc->language = 'javascript';
    	
    	// Build the maps/reduces from the files in the config dir
    	$mapDir = sfConfig::get('sf_config_dir').'/couchdb/';
    	$designDoc->views = self::getViewsFromConfig($mapDir);

        $designDoc->save();
    }
    
    private static function getViewsFromConfig($dir)
    {
    	$views = array();
    	foreach (glob($dir.'*.js') as $fileName) {
    		preg_match('/.*\/(.*)_map.js/', $fileName, $filematches);
    		if (isset($filematches[1])) {
    			
    			$viewName = $filematches[1];
    			$views[$viewName] = array();
    			$views[$viewName]['map'] = file_get_contents($fileName);
		    	
    			$reduceFileName = $dir.$viewName.'_reduce.js';
		        if (file_exists($reduceFileName)) {
		          $views[$viewName]['reduce'] = file_get_contents($reduceFileName);
		        }
    		}
    	}
        
    	return $views;
    } 
}