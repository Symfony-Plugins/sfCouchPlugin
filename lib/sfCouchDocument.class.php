<?php
/**
 * Basic document
 *
 * @package Core
 * @version $Revision: 97 $
 * @license http://www.gnu.org/licenses/lgpl-3.0.txt LGPL
 */
class sfCouchDocument
{
    /**
     * Object storing all the document properties as public attributes. This
     * way it is easy to serialize using json_encode.
     *
     * @var StdClass
     */
    protected $storage;

    /**
     * List of required properties. For each required property, which is not
     * set, a validation exception will be thrown on save.
     *
     * @var array
     */
    protected $requiredProperties = array();

    /**
     * Flag, indicating if current document has already been modified
     *
     * @var bool
     */
    protected $modified = false;

    /**
     * Flag, indicating if current document is a new one.
     *
     * @var bool
     */
    protected $newDocument = true;

    /**
     * List of special properties, which are available beside the document
     * specific properties.
     *
     * @var array
     */
    protected static $specialProperties = array(
        '_id',
        '_rev',
        '_attachments'
    );

    /**
     * List of new attachements to the document.
     *
     * @var array
     */
    protected $newAttachments = array();

    /**
     * Set this before calling static functions.
     *
     * @var string
     */
    public static $docType = null;

    /**
     * Construct new document
     *
     * Construct new document
     *
     * @return void
     */
    public function __construct($id = null)
    {
        $this->storage = new StdClass();
        $this->storage->_id = null;
        $this->storage->_attachments = array();

        if ($id) {
          $this->storage->_id = $id;
          $this->fetchById($id);
        }
    }

    /**
     * Get document property
     *
     * Get property from document
     *
     * @param string $property
     * @return mixed
     */
    public function __get( $property )
    {
        return $this->storage->$property;
    }
    
    /**
     * Is this a ne document?
     *
     * @return boolean
     */
    public function isNew()
    {
        return $this->newDocument;
    }

    /**
     * Set a property value
     *
     * Set a property value, which will be validated using the assigned
     * validator. Setting a property will mark the document as modified, so
     * that you know when to store the object.
     *
     * @param string $property
     * @param mixed $value
     * @return void
     */
    public function __set( $property, $value )
    {
        $this->storage->$property = $value;
        $this->modified = true;
    }

    /**
     * Check if document property is set
     *
     * Check if document property is set
     *
     * @param string $property
     * @return boolean
     */
    public function __isset( $property )
    {
        // Check if property exists as a custom document property
        if ( array_key_exists( $property, $this->properties ) ||
             in_array( $property, self::$specialProperties ) )
        {
            return true;
        }

        // If none of the above checks passed, the request is invalid.
        return false;
    }

    /**
     * Set values from a response object
     *
     * Set values of the document from the response object, if they are
     * available in there.
     *
     * @param sfCouchResponse $response
     * @return void
     */
    protected function fromResponse( sfCouchResponse $response )
    {
        // Set all document property values from response, if available in the
        // response.
        //
        // Also fill a revision object with the set attributtes, so that the
        // current revision is also available in history, and it is stored,
        // when the object is modified and stored again.
        $revision = new StdClass();
        $revision->_date = time();
        foreach ( $response->getFullDocument() as $property => $v )
        {
            $this->storage->$property = $v;
            $revision->$property = $v;
        }

        // Document freshly loaded, so it is not modified, and not a new
        // document...
        $this->modified = false;
        $this->newDocument = false;
    }

    /**
     * Get document by ID
     *
     * Get document by ID and return a document objetc instance for the fetch
     * document.
     *
     * @param string $id
     * @return sfCouchDocument
     */
    public function fetchById( $id )
    {
        // If a fetch is called with an empty ID, we throw an exception, as we
        // would get database statistics otherwise, and the following error may
        // be hard to debug.
        if ( empty( $id ) )
        {
            throw new sfException('No document ID specified.');
        }

        // Fetch object from database
        $db = sfCouchConnection::getInstance();
        $response = $db->get(
            urlencode( $id )
        );

        // Create document contents from fetched object
        if (!empty($response)) {
        	$this->fromResponse( $response );
        }

        return $this;
    }


    /**
     * Get ID from document
     *
     * The ID normally should be calculated on some meaningful / unique
     * property for the current ttype of documents. The returned string should
     * not be too long and should not contain multibyte characters.
     *
     * You can return null instead of an ID string, to trigger the ID
     * autogeneration.
     *
     * @return mixed
     */
     protected function generateId()
     {
       return null;
     }

    /**
     * Check if all requirements are met
     *
     * Checks if all required properties has been set. Returns an array with
     * the properties, whcih are required but not set, or true if all
     * requirements are fulfilled.
     *
     * @return mixed
     */
    public function checkRequirements()
    {
        // Iterate over properties and check if they are set and not null
        $errors = array();
        foreach ( $this->requiredProperties as $property )
        {
            if ( !isset( $this->storage->$property ) ||
                 ( $this->storage->$property === null ) )
            {
                $errors[] = $property;
            }
        }

        // If error array is still empty all requirements are met
        if ( $errors === array() )
        {
            return true;
        }

        // Otherwise return the array with errors
        return $errors;
    }

    /**
     * Save the document
     *
     * If thew document has not been modfied the method will immediatly exit
     * and return false. If the document has been been modified, the modified
     * document will be stored in the database, keeping all the old revision
     * intact and return true on success.
     *
     * On successful creation the (generated) ID will be returned.
     *
     * @return string
     */
    public function save()
    {

        // Ensure all requirements are checked, otherwise bail out with a
        // runtime exception.
        if ( $this->checkRequirements() !== true )
        {
            throw new sfException(
                "Required properties for this document aren't set."
            );
        }

        // Check if we need to store the stuff at all
        if ( ( $this->modified === false ) &&
             ( $this->newDocument !== true ) )
        {
            return false;
        }
        
        // Generate a new ID, if this is a new document, otherwise reuse the
        // existing document ID.
        if ( $this->storage->_id === null )
        {
            $this->storage->_id = $this->generateId();
        }

        // Do not send an attachment array, if there aren't any attachements
        if ( !isset( $this->storage->_attachments ) ||
             !count( $this->storage->_attachments ) )
        {
            unset( $this->storage->_attachments );
        }

        // If the document ID is null, the server should autogenerate some ID,
        // but for this we need to use a different request method.
        $db = sfCouchConnection::getInstance();
        if ( $this->storage->_id === null )
        {
            // Store document in database
            unset( $this->storage->_id );
            $response = $db->post(
                json_encode( $this->storage )
            );
        }
        else
        {
            // Store document in database
            $response = $db->put(
                urlencode( $this->_id ),
                json_encode( $this->storage )
            );
        }
		
        if (empty($response)) {
        	return null;
        }
        return $this->storage->_id = $response->id;
    }

    /**
     * Get ID string from arbritrary string
     *
     * To calculate an ID string from an sfCouchrary string, first iconvs
     * tarnsliteration abilities are used, and after that all, but common ID
     * characters, are replaced by the given replace string, which defaults to
     * _.
     *
     * @param string $string
     * @param string $replace
     * @return string
     */
    protected function stringToId( $string, $replace = '_' )
    {
        // First translit string to ASCII, as this characters are most probably
        // supported everywhere
        $string = iconv( 'UTF-8', 'ASCII//TRANSLIT', $string );

        // And then still replace any obscure characers by _ to ensure nothing
        // "bad" happens with this string.
        $string = preg_replace( '([^A-Za-z0-9.-]+)', $replace, $string );

        // Additionally we convert the string to lowercase, so that we get case
        // insensitive fetching
        return strtolower( $string );
    }

    /**
     * Attach file to document
     *
     * The file passed to the method will be attached to the document and
     * stored in the database. By default the filename of the provided file
     * will be ued as a name, but you may optionally specify a name as the
     * second parameter of the method.
     *
     * You may optionally specify a custom mime type as third parameter. If set
     * it will be used, but not verified, that it matches the actual file
     * contents. If left empty the mime type defaults to
     * 'application/octet-stream'.
     *
     * @param string $fileName
     * @param string $name
     * @param string $mimeType
     * @return void
     */
    public function attachFile( $fileName, $name = false, $mimeType = false )
    {
        $name = ( $name === false ? basename( $fileName ) : $name );
        $this->storage->_attachments[$name] = array(
            'type'         => 'base64',
            'data'         => base64_encode( file_get_contents( $fileName ) ),
            'content_type' => $mimeType === false ? 'application/octet-stream' : $mimeType,
        );
        $this->modified = true;
    }

    /**
     * Get file contents
     *
     * Get the contents of an attached file as a sfCouchDataResponse.
     *
     * @param string $fileName
     * @return String tempFileName
     */
    public function getFile( $fileName )
    {
        if ( !isset( $this->storage->_attachments[$fileName] ) )
        {
            return null;
        }

        $db = sfCouchConnection::getInstance();
        $response = $db->get(
            urlencode( $this->_id ) . '/' . $fileName,
            null, true
        );

        if (is_null($response)) {
        	return null;
        }
        
        $fileName = tempnam(sys_get_temp_dir(), 'sfCouch_');
		file_put_contents($fileName, $response);
        
        return $fileName;
    }
}