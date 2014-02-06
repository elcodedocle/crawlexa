<?php
/*
  Copyright 2011 3e software house & interactive agency

  Licensed under the Apache License, Version 2.0 (the "License");
  you may not use this file except in compliance with the License.
  You may obtain a copy of the License at

  http://www.apache.org/licenses/LICENSE-2.0

  Unless required by applicable law or agreed to in writing, software
  distributed under the License is distributed on an "AS IS" BASIS,
  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
  See the License for the specific language governing permissions and
  limitations under the License.
 */

require_once( dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'phpwebdriver' . DIRECTORY_SEPARATOR . 'WebDriver.php' );

/**
*  Base class for functional tests using webdriver.
*  It provides interface like classic selenium test class.
*  @author kolec
*/
class CWebDriverTestCase extends PHPUnit_Framework_TestCase {

    /**
     *
     * @var WebDriver
     */
	protected $webdriver;
    public $baseUrl;

	const LOAD_DELAY = 500000; //0.5 sec. delay
    const STEP_WAITING_TIME = 0.5; //when synchronous request is simulated this is single step waiting time
    const MAX_WAITING_TIME = 4; //when synchronous request is simulated this is total timeout when witing for result

    protected function setUp( $host='localhost', $port=4444, $browser='firefox' ) {
		parent::setUp();
		if(is_array($this->fixtures))
			$this->getFixtureManager()->load($this->fixtures);
		$this->webdriver = new WebDriver( $host, $port );
        $this->webdriver->connect( $browser );
    }

    protected function tearDown() {
		if( $this->webdriver ) {
			$this->webdriver->close();
		}
    }
    public function refresh(){
        $this->webdriver->refresh();
    }

    public function back(){
        $this->webdriver->back();
    }

    public function forward(){
        $this->webdriver->forward();
    }

    public function focusFrame($frameId){
        $this->webdriver->focusFrame($frameId);
    }

	public function setBrowserUrl( $url ) {
		$this->baseUrl = $url;
	}

    public function open( $url, $check_id = '' ) {
        if( strlen( $url ) > 0 && $url[0] !== '/' ) {
            $url = '/' . $url;
        }
        $urlToOpen = $this->baseUrl . $url;

        $this->webdriver->get( $urlToOpen );
        if( !empty( $check_id ) ) {
            return $this->getElement( LocatorStrategy::id, $check_id );
		} else {
			usleep( self::LOAD_DELAY );
		}
    }

	public function getBodyText() {
		$html = $this->webdriver->getPageSource();
		$body = preg_replace( '/(^(.*)<body[^>]*>)|(<\/body>(.*)$)/si', '', $html );
		return $body;
	}

	public function isTextPresent( $text ) {
		$found = false;
		$i = 0;
		do {
			$html = $this->webdriver->getPageSource();
			if( is_string( $html ) ) {
				$found = !( strpos( $html, $text ) === false );
			}
			if( !$found ) {
				sleep( self::STEP_WAITING_TIME );
				$i += self::STEP_WAITING_TIME;
			}
		} while( !$found && $i <= self::MAX_WAITING_TIME );
		return $found;
	}

    public function getAttribute( $xpath ) {
        $body = $this->getBodyText();
        $xml = new SimpleXMLElement( $body );
        $nodes = $xml->xpath( "$xpath" );
        return $nodes[0][0];
    }

    public function type( $element_id, $textToType ) {
        $element = $this->getElement( LocatorStrategy::id, $element_id );
        if( isset( $element ) ) {
            $element->sendKeys( array( $textToType ) );
        }
    }

    public function typeKeyAfterKey( $element_name, $textToType ) {
        $element = $this->getElement( LocatorStrategy::id, $element_name );
        if( isset( $element ) ) {
            $length = mb_strlen( $textToType );
            for( $i = 0; $i < $length; $i++ ) {
                $element->sendKeys( array( mb_substr( $textToType, $i, 1 ) ) );
                sleep( 0.1 );
            }
        }
    }

    public function clear( $element_name ) {
        $element = $this->getElement( LocatorStrategy::id, $element_name );
        if( $element ) {
            $element->clear();
        }
    }

    public function submit( $element_name ) {
        $element = $this->getElement( LocatorStrategy::id, $element_name );
        if( isset( $element ) ) {
            $element->submit();
            usleep( self::LOAD_DELAY );
        }
    }

    public function click( $element_name ) {
        $element = $this->getElement( LocatorStrategy::id, $element_name );
        if( isset( $element ) ) {
            $element->click();
            usleep( self::LOAD_DELAY );
        }
    }

    public function select( $select_id, $option_text ) {
        $element = $this->getElement( LocatorStrategy::id, $select_id );
        $option = $element->findOptionElementByText( $option_text );
        $option->click();
    }

    /**
     *
     * @param string $element_name
     * @return WebElement found element or null
     */
    public function getElementByIdOrName( $element_name ) {
        try {
            $element = $this->webdriver->findElementBy(LocatorStrategy::id, $element_name);
        } catch (NoSuchElementException $ex) {
            try {
                $element = $this->webdriver->findElementBy(LocatorStrategy::name, $element_name);
            } catch (NoSuchElementException $ex) {
                $element = null;
            }
        }
        return $element;
    }

    /**
     *
     * @param type $strategy
     * @param type $name
     * @param boolean $failIfNotExists whether should fail test if element not exists
     * @return WebElement
     */
    public function getElement( $strategy, $name, $failIfNotExists = true ) {
        $i = 0;
        do {
            try {
                $element = $this->webdriver->findElementBy( $strategy, $name );
            } catch( NoSuchElementException $e ) {
                print_r( "\nWaiting for \"" . $name . "\" element to appear...\n" );
                sleep( self::STEP_WAITING_TIME );
                $i += self::STEP_WAITING_TIME;
            }
        } while( !isset( $element ) && $i <= self::MAX_WAITING_TIME );
        if( !isset( $element ) && $failIfNotExists ) {
            $this->fail( "Element has not appeared after " . self::MAX_WAITING_TIME . " seconds." );
		}
        return $element;
    }


	/**
	 * @var array a list of fixtures that should be loaded before each test method executes.
	 * The array keys are fixture names, and the array values are either AR class names
	 * or table names. If table names, they must begin with a colon character (e.g. 'Post'
	 * means an AR class, while ':Post' means a table name).
	 * Defaults to false, meaning fixtures will not be used at all.
	 */
	protected $fixtures=false;

	/**
	 * PHP magic method.
	 * This method is overridden so that named fixture data can be accessed like a normal property.
	 * @param string $name the property name
	 * @return mixed the property value
	 */
	public function __get($name)
	{
		if(is_array($this->fixtures) && ($rows=$this->getFixtureManager()->getRows($name))!==false)
			return $rows;
		else
			throw new Exception("Unknown property '$name' for class '".get_class($this)."'.");
	}

	/**
	 * PHP magic method.
	 * This method is overridden so that named fixture ActiveRecord instances can be accessed in terms of a method call.
	 * @param string $name method name
	 * @param string $params method parameters
	 * @return mixed the property value
	 */
	public function __call($name,$params)
	{
		if(is_array($this->fixtures) && isset($params[0]) && ($record=$this->getFixtureManager()->getRecord($name,$params[0]))!==false) {
			return $record;
		} elseif( method_exists( $this->webdriver, $name ) ) {
			return call_user_func_array( array( $this->webdriver, $name ), $params );
		} else {
			return parent::__call($name,$params);
		}
	}

	/**
	 * @return CDbFixtureManager the database fixture manager
	 */
	public function getFixtureManager()
	{
		return Yii::app()->getComponent('fixture');
	}

	/**
	 * @param string $name the fixture name (the key value in {@link fixtures}).
	 * @return array the named fixture data
	 */
	public function getFixtureData($name)
	{
		return $this->getFixtureManager()->getRows($name);
	}

	/**
	 * @param string $name the fixture name (the key value in {@link fixtures}).
	 * @param string $alias the alias of the fixture data row
	 * @return CActiveRecord the ActiveRecord instance corresponding to the specified alias in the named fixture.
	 * False is returned if there is no such fixture or the record cannot be found.
	 */
	public function getFixtureRecord($name,$alias)
	{
		return $this->getFixtureManager()->getRecord($name,$alias);
	}
}
