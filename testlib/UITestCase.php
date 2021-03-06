<?php
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;

abstract class UITestCase extends BaseTestCase
{
    /**
     *
     * @var WebDriver
     */
    protected $driver;
    private $testId;

    public function setUp()
    {
        parent::setUp();
        $this->testId = uniqid();
        switch(getenv('CFX_WEB_BROWSER')) {
            case 'chrome':
                $browser = DesiredCapabilities::chrome();
                break;
            case 'firefox':
                $browser = DesiredCapabilities::firefox();
                break;
        }
        $this->driver = RemoteWebDriver::create(
            getenv('CFX_WEB_DRIVER'), $browser
        );
    }
    
    public function tearDown()
    {
        parent::tearDown();
        if(is_object($this->driver)) {
            $this->driver->close();
        }
    }
    
    protected function open()
    {
        $this->driver->get(getenv('CFX_TEST_WEB_HOST'));
        $this->driver->manage()->addCookie(
            ['name' => 'CFX_TEST_ID', 'value' => $this->testId]
        );
        $this->navigateTo('');
    }
    
    protected function login()
    {
        $this->driver->findElement(WebDriverBy::id('username'))->sendKeys('dev');
        $this->driver->findElement(WebDriverBy::id('password'))->sendKeys('dev');
        $this->driver->findElement(WebDriverBy::cssSelector('#fapi-submit-area > input'))->click();        
    }

    protected function getArrayDataSet()
    {
        return [
            'common.roles' => [
                ['role_id' => 1, 'role_name' => 'Super User']
            ],
            'auth.users_roles' => [
                ['users_roles_id' => 1, 'user_id' => 1, 'role_id' => 1]
            ],
            'common.users' => [
                [
                    'user_id' => 1,
                    'user_name' => 'dev',
                    'password' => md5('dev'),
                    'role_id' => 1,
                    'first_name' => 'Development',
                    'last_name' => 'User',
                    'email' => 'dev@nthc.com.gh',
                    'user_status' => 1
                ]
            ]
        ]
        + $this->getUIArrayDataSet();
    }
    
    protected function navigateTo($path)
    {
        $this->driver->navigate()->to(getenv('CFX_TEST_WEB_HOST') . $path);
    }

    protected function find($selector)
    {
        return $this->driver->findElement(WebDriverBy::cssSelector($selector));
    }
    
    public function run(PHPUnit_Framework_TestResult $result = NULL) {
        if($result === NULL) {
            $result = $this->createResult();
        }      
        
        parent::run($result);
        
        if($result->getCollectCodeCoverageInformation()) {
            $data = unserialize(file_get_contents(getenv('CFX_TEST_WEB_HOST') . "/vendor/nthc/cfx/testlib/coverage.php?id={$this->testId}"));
            $result->getCodeCoverage()->append(
                $data,
                $this
            );
        }
        
        return $result;
    }
    
    protected function fillForm($selector, $data)
    {
        foreach($data as $key => $value)
        {
            $element = $this->driver->findElement(
                WebDriverBy::cssSelector("$selector *[name=$key]")
            );
            switch($element->getTagName())
            {
                case 'input': 
                case 'textarea':
                    if($element->getAttribute('type') === 'checkbox') {
                        if(($value == true && !$element->isSelected() && $element->isDisplayed())) {
                            $element->click();
                        } else {
                            $element->clear();
                        }
                    } else {
                        $element->sendKeys($value);
                    }
                    break;
                case 'select':
                    $element->findElement(WebDriverBy::cssSelector("option[value='$value']"))->click();
                    break;
            }
        }
    }
    
    protected function setModelSearchField($name, $value)
    {
        $this->find("#{$name}_search_entry")->sendKeys($value);
        sleep(3);
        $this->find("#{$name}_search_entry_{$value}")->click();
    }
    
    protected function setRelationshipField($name, $main, $sub)
    {
        $this->find("#{$name}_main")->findElement(WebDriverBy::cssSelector("option[value='$main']"))->click();
        sleep(3);
        $this->find("#{$name}")->findElement(WebDriverBy::cssSelector("option[value='$sub']"))->click();
    }
    
    protected function submitForm($selector)
    {
        $this->driver->findElement(WebDriverBy::cssSelector("$selector"))->submit();
    }
    
    protected function onNotSuccessfulTest(\Exception $e)
    {
        if(is_object($this->driver)) {
            $this->driver->takeScreenshot("screenshots/" . uniqid() . ".png");
        }
        parent::onNotSuccessfulTest($e);
    }

    abstract protected function getUIArrayDataSet();
}
