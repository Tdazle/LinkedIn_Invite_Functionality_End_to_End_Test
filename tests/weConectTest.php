<?php

require_once 'vendor/autoload.php';

use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\TimeoutException;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

class LinkedInInviteTest extends PHPUnit\Framework\TestCase
{
    protected RemoteWebDriver $webDriver;
    protected array $profileLinks;
    protected array $failedProfiles;
    protected array $alreadyConnectedProfiles;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $capabilities = DesiredCapabilities::chrome();
        $this->webDriver = RemoteWebDriver::create('http://localhost:4444/wd/hub', $capabilities);

        // 1. Leading Profiles
        $this->profileLinks = $this->readProfileLinksFromFileOrAPI();

        // Initialize failed profiles array
        $this->failedProfiles = [];
    }

    /**
     * @return void
     * @throws Exception
     */
    public function testLinkedInInviteFunctionality(): void
    {
        // 2. Login to LinkedIn
        $credentials = $this->readLoginCredentialsFromEnv();
        $email = $credentials['email'];
        $password = $credentials['password'];
        $this->loginToLinkedIn($email, $password);

        // 3. Visit Each Profile Page
        foreach ($this->profileLinks as $profileLink) {
            try {
                $this->visitProfilePage($profileLink);
            } catch (Exception $e) {
                $this->handleProfileNotFoundError($profileLink);
            }

            // 4. Connect to Profile
            if (!$this->isAlreadyConnected($profileLink)) {
                $this->sendConnectionRequest($profileLink);
            } else {
                $this->handleAlreadyConnectedProfile($profileLink);
            }
        }

        // 5. Generate a Report
        $this->generateTestReport(count($this->profileLinks), count($this->failedProfiles));

        // Close the LinkedIn session
        $this->closeLinkedInSession();
    }

    /**
     * @return array
     */
    private function readProfileLinksFromFileOrAPI(): array
    {
        // Read profile links from a file (e.g., profiles.txt)
        $fileContents = file_get_contents('profiles.txt');
        $profileLinks = explode("\n", $fileContents);

        // Remove empty elements from the array
        return array_filter(array_map('trim', $profileLinks));
    }

    /**
     * @return array
     * @throws Exception
     */
    private function readLoginCredentialsFromEnv(): array
    {
        // Check if environment variables are set
        $email = getenv('LINKEDIN_EMAIL');
        $password = getenv('LINKEDIN_PASSWORD');

        // Validate the presence of both email and password
        if (!$email || !$password) {
            throw new Exception('LinkedIn credentials are not set in environment variables.');
        }

        return ['email' => $email, 'password' => $password];
    }

    /**
     * @param string $email
     * @param string $password
     * @return void
     */
    private function loginToLinkedIn(string $email, string $password): void
    {
        // Navigate to the LinkedIn login page
        $this->webDriver->get('https://www.linkedin.com/login');

        // Find the email and password input fields and submit button
        $emailField = $this->webDriver->findElement(WebDriverBy::name('session_key'));
        $passwordField = $this->webDriver->findElement(WebDriverBy::name('session_password'));
        $submitButton = $this->webDriver->findElement(WebDriverBy::xpath('//button[@type="submit"]'));

        // Enter the email and password
        $emailField->sendKeys($email);
        $passwordField->sendKeys($password);

        // Click the submit button to log in
        $submitButton->click();

        // Wait for the login to complete
        sleep(2); // Example: wait for 2 seconds
    }

    /**
     * @param $profileLink
     * @return void
     * @throws Exception
     */
    private function visitProfilePage($profileLink): void
    {
        // Construct the full URL of the LinkedIn profile
        $fullProfileUrl = 'https://www.linkedin.com/in/' . $profileLink;

        // Navigate to the LinkedIn profile page
        $this->webDriver->get($fullProfileUrl);

        // Check if the profile page is loaded successfully
        $this->waitForProfilePageToLoad();
    }

    /**
     * @return void
     * @throws Exception
     */
    private function waitForProfilePageToLoad(): void
    {
        // Implement appropriate waiting mechanisms
        try {
            $this->webDriver->wait(10, 500)->until(
                WebDriverExpectedCondition::titleContains('LinkedIn')
            );
        } catch (NoSuchElementException|TimeoutException $e) {
        }

    }

    /**
     * @param string $profileLink
     * @return void
     * @throws Exception
     */
    private function handleProfileNotFoundError(string $profileLink): void
    {
        // Log the error (you can replace this with your preferred logging mechanism)
        error_log("Profile not found: $profileLink");

        // Mark the profile as failed for reporting purposes
        $this->failedProfiles[] = $profileLink;

        // You might also want to throw an exception if the error should stop the test execution
        throw new Exception("Profile not found: $profileLink");
    }

    /**
     * @param string $profileLink
     * @return bool
     */
    private function isAlreadyConnected(string $profileLink): bool
    {
        // Construct the full URL of the LinkedIn profile
        $fullProfileUrl = 'https://www.linkedin.com/in/' . $profileLink;

        // Navigate to the LinkedIn profile page
        $this->webDriver->get($fullProfileUrl);

        // Check if the profile is already connected
        // Look for elements on the page that indicate a connection status
        $connectButton = $this->webDriver->findElement(WebDriverBy::xpath("//button[contains(text(), 'Connect')]"));

        // If the element is found, it indicates that the profile is already connected
        return $connectButton->getAttribute('class') === 'already-connected';
    }

    /**
     * @param string $profileLink
     * @return void
     * @throws Exception
     */
    private function sendConnectionRequest(string $profileLink): void
    {
        // Construct the full URL of the LinkedIn profile
        $fullProfileUrl = 'https://www.linkedin.com/in/' . $profileLink;

        // Navigate to the LinkedIn profile page
        $this->webDriver->get($fullProfileUrl);

        // Check if the "Connect" button is present on the profile page
        try {
            $connectButton = $this->webDriver->findElement(WebDriverBy::xpath("//button[contains(text(), 'Connect')]"));
            $connectButton->click();

            // Wait for the connection modal to appear (add appropriate waits based on your application)
            $this->waitForConnectionModalToAppear();

            // Add a personalized message to the connection request
            $messageField = $this->webDriver->findElement(WebDriverBy::name('message'));
            $messageField->sendKeys('Your custom message');

            // Click the "Send" button to send the connection request
            $sendButton = $this->webDriver->findElement(WebDriverBy::xpath("//button[contains(text(), 'Send')]"));
            $sendButton->click();

            // Wait for the connection request to be sent (add appropriate waits based on your application)
            $this->waitForConnectionRequestSent();
        } catch (Exception $e) {
            // Handle the case when the "Connect" button or other elements are not found
            $this->handleConnectionError($profileLink);
        }
    }

    /**
     * @return void
     * @throws NoSuchElementException
     * @throws TimeoutException
     */
    private function waitForConnectionModalToAppear(): void
    {
        // You can use WebDriverWait or other appropriate waiting mechanisms
        $this->webDriver->wait(10, 500)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(
                WebDriverBy::xpath('//div[@class="connection-modal"]')
            )
        );
    }

    /**
     * @return void
     * @throws NoSuchElementException
     * @throws TimeoutException
     */
    private function waitForConnectionRequestSent(): void
    {
        // You can use WebDriverWait or other appropriate waiting mechanisms
        $this->webDriver->wait(10, 500)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(
                WebDriverBy::xpath('//div[@class="request-sent-message"]')
            )
        );
    }

    /**
     * @throws Exception
     */
    private function handleConnectionError($profileLink)
    {
        // Log the error (you can replace this with your preferred logging mechanism)
        error_log("Error connecting to profile: $profileLink");

        // Mark the profile as failed for reporting purposes
        $this->failedProfiles[] = $profileLink;

        // You might also want to throw an exception if the error should stop the test execution
        throw new Exception("Error connecting to profile: $profileLink");
    }

    /**
     * @param $profileLink
     * @return void
     */
    private function handleAlreadyConnectedProfile($profileLink): void
    {
        // Log the information (you can replace this with your preferred logging mechanism)
        error_log("Profile is already connected: $profileLink");

        // You might also want to add the profile to a list of already connected profiles for reporting purposes
        $this->alreadyConnectedProfiles[] = $profileLink;
    }


    /**
     * @param int $totalProfiles
     * @param int $failedProfiles
     * @return void
     */
    private function generateTestReport(int $totalProfiles, int $failedProfiles): void
    {
        // Implement logic to generate a test report
        $reportContent = "LinkedIn Invite Functionality Test Report\n";
        $reportContent .= "Total Profiles Processed: $totalProfiles\n";
        $reportContent .= "Profiles Failed: $failedProfiles\n";

        file_put_contents('linkedin_invite_test_report.txt', $reportContent);
    }

    /**
     * @return void
     */
    private function closeLinkedInSession(): void
    {
        // Implement logic to close the LinkedIn session
        $this->webDriver->quit();
    }
}
