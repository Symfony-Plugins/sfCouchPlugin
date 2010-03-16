<?php
/**
 * Response factory to create response objects from JSON results
 *
 * @package Core
 * @version $Revision: 44 $
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt LGPL
 */
class sfCouchResponse
{
    /**
     * Array containing all response properties
     *
     * @var array
     */
    protected $properties;

    /**
     * Construct response object from JSON result
     *
     * @param array $body
     * @return void
     */
    public function __construct( array $body, $fromArray = false)
    {
        if ($fromArray) {
          $this->properties['data'] = $response;
        }
        else {
          // Set all properties as virtual readonly repsonse object properties.
          foreach ( $body as $property => $value )
          {
              $this->properties[$property] = $value;
          }
        }
    }

    /**
     * Get full document
     *
     * @return array
     */
    public function getFullDocument()
    {
        return $this->properties;
    }

    /**
     * Get available property
     *
     * Receive response object property, if available. If the property is not
     * available, the method will throw an exception.
     *
     * @param string $property
     * @return mixed
     */
    public function __get( $property )
    {
        // Check if such an property exists at all
        if ( !isset( $this->properties[$property] ) )
        {
            throw new sfException( $property );
        }

        return $this->properties[$property];
    }

    /**
     * Check if property exists.
     *
     * Check if property exists.
     *
     * @param string $property
     * @return bool
     */
    public function __isset( $property )
    {
        return isset( $this->properties[$property] );
    }

    /**
     * Silently ignore each write access on response object properties.
     *
     * @param string $property
     * @param mixed $value
     * @return bool
     */
    public function __set( $property, $value )
    {
        return false;
    }

    /**
     * Parse a server response
     *
     * Parses a server response depending on the response body and the HTTP
     * status code.
     *
     * For put and delete requests the server will just return a status,
     * wheather the request was successfull, which is represented by a
     * sfCouchStatusResponse object.
     *
     * For all other cases most probably some error occured, which is
     * transformed into a sfCouchResponseErrorException, which will be thrown
     * by the parse method.
     *
     * @param array $headers
     * @param string $body
     * @return sfCouchResponse
     */
    public static function parse( array $headers, $body)
    {
        $response = json_decode( $body, true );

        // To detect the type of the response from the couch DB server we use
        // the response status which indicates the return type.
        switch ( $headers['status'] )
        {
            case 200:
                // The HTTP status code 200 - OK indicates, that we got a document
                // or a set of documents as return value.
                //
                // To check wheather we received a set of documents or a single
                // document we can check for the document properties _id or
                // _rev, which are always available for documents and are only
                // available for documents.
                if ( $body[0] === '[' )
                {
                    return new sfCouchResponse( $response, true);
                }
                elseif ( isset( $response->_id ) || isset( $response->rows ) )
                {
                    return new sfCouchResponse( $response );
                }

                // Otherwise fall back to a plain status response. No break.

            case 201:
            case 202:
                // The following status codes are given for status responses
                // depending on the request type - which does not matter here any
                // more.
                return new sfCouchResponse( $response );

            default:
                // All other unhandled HTTP codes are for now handled as an error.
                // This may not be true, as lots of other status code may be used
                // for valid repsonses.
                throw new sfException( $headers['status'] );
        }
    }
}


