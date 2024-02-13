package testCases;

import org.testng.Assert;
import org.testng.annotations.Test;

import pageObjects.AccountRegistrationPage;
import pageObjects.HomePage;
import testBase.BaseClass;

public class TC_001_AccountRegistrationPageTest extends BaseClass {

	@Test(groups = {"regression", "master"})	// we have to add master to all test classes except Data Driven Test Cases
	public void testAccountRegistration() {
		try {
			logger.info("**** Starting TC_001_AccountRegistrationPageTest ****");
			
			HomePage homePage = new HomePage(driver);

			homePage.clickMyAccount();
			logger.info("clicked on My Account link");
			
			homePage.clickRegister();
			logger.info("clicked on Registration link");
			
			AccountRegistrationPage registrationPage = new AccountRegistrationPage(driver);
			BaseClass baseClass = new BaseClass();

			logger.info("Setting user details");
			registrationPage.setFirstName(baseClass.randomString());
			registrationPage.setLastName(baseClass.randomString());
			registrationPage.setEmail(baseClass.randomString() + "@gmail.com");
			registrationPage.setTelephone(baseClass.randomNumber());

			String pwd = baseClass.randomAlphaNumeric();
			registrationPage.setPassword(pwd);
			registrationPage.setPasswordConfirm(pwd);
			
			registrationPage.checkAgree();
			logger.info("clicked on policy agreement ");
			
			registrationPage.clickContinue();
			logger.info("clicked on continue button");
			
			logger.info("validating the assert statement");
			boolean accountCreatedStatus = registrationPage.isAccountCreated();
			
			if(accountCreatedStatus) {
				logger.info("Test case passed");
				Assert.assertTrue(true);
			} 
			else {
				logger.error("Test case failed");
				Assert.fail();
			}

		} catch (Exception e) {
			logger.error("Test Failed");
			logger.error("Some exception occured {}: "+e);
			Assert.fail();
		}
		
		logger.info("**** Finished TC_001_AccountRegistrationPageTest ****");
		
	}

}
