package testBase;

import java.io.File;
import java.io.FileReader;
import java.io.IOException;
import java.net.URL;
import java.text.SimpleDateFormat;
import java.time.Duration;
import java.util.Date;
import java.util.Properties;

import org.apache.commons.lang3.RandomStringUtils;
import org.apache.logging.log4j.LogManager;
import org.apache.logging.log4j.Logger;
import org.openqa.selenium.OutputType;
import org.openqa.selenium.Platform;
import org.openqa.selenium.TakesScreenshot;
import org.openqa.selenium.WebDriver;
import org.openqa.selenium.chrome.ChromeDriver;
import org.openqa.selenium.edge.EdgeDriver;
import org.openqa.selenium.firefox.FirefoxDriver;
import org.openqa.selenium.remote.DesiredCapabilities;
import org.openqa.selenium.remote.RemoteWebDriver;
import org.testng.annotations.AfterClass;
import org.testng.annotations.BeforeClass;
import org.testng.annotations.Parameters;

public class BaseClass {

	public static WebDriver driver;  // static because we refer same driver instance in ExtentReportManager (for capturing screenshot). 
	public Logger logger;     // from log4j dependency
	public Properties propertyFile;

	@BeforeClass(groups = {"regression", "sanity", "master"})    // add all or total groups in setup and tearDown methods
	@Parameters({"browser", "os"})
	public void setUp(String browser, String os) throws IOException {
		
		// Setting Logs..
		logger = LogManager.getLogger(this.getClass());   // from log4j dependency\
		
		// Getting properties from config.properties file..
		FileReader file = new FileReader(".\\src\\test\\resources\\config.properties");
		propertyFile = new Properties();
		propertyFile.load(file);
		
		// launching browser on parameter conditions from XML and from config.properties file..
		String env = propertyFile.getProperty("execution_env").toLowerCase();
		
		if(env.equals("remote")) {
			
			String nodeURL = "http://localhost:4444/wd/hub";
			DesiredCapabilities capabilities = new DesiredCapabilities();

			// FOR OS
			switch (os.toLowerCase()) {
			case "windows": capabilities.setPlatform(Platform.WIN10); break;
			case "mac": capabilities.setPlatform(Platform.MAC); break;
			default: System.out.println("No matching os found...");
						return;
			}
			
			// FOR BROWSER
			switch (browser.toLowerCase()) {
            case "chrome": capabilities.setBrowserName("chrome"); break;
            case "firefox": capabilities.setBrowserName("firefox"); break;
            case "edge": capabilities.setBrowserName("MicrosoftEdge"); break;
            default: System.out.println("No matching browser found..");
                        return;
            }
			
			driver = new RemoteWebDriver(new URL(nodeURL), capabilities);
		}
		else {
			switch (browser.toLowerCase()) {
			case "chrome": driver = new ChromeDriver(); break;
			case "firefox": driver = new FirefoxDriver(); break;
			case "edge": driver = new EdgeDriver(); break;
			default: System.out.println("No matching browser found..");
						return;
			}
		}
		
		driver.manage().deleteAllCookies();
		
		driver.manage().timeouts().implicitlyWait(Duration.ofSeconds(10));
		driver.get(propertyFile.getProperty("appURL"));
		driver.manage().window().maximize();
	}

	@AfterClass(groups = {"regression", "sanity", "master"})
	public void tearDown() {
		driver.quit();
	}

	public String randomString() {
		String generatedString = RandomStringUtils.randomAlphabetic(5);
		return generatedString;
	}

	public String randomNumber() {
		String generatedNumber = RandomStringUtils.randomNumeric(10);
		return generatedNumber;
	}

	public String randomAlphaNumeric() {
		String string = RandomStringUtils.randomAlphabetic(5);
		String number = RandomStringUtils.randomNumeric(5);

		return (string + "@" + number);
	}
	
	public String captureScreen(String testMethodName) {
		String timeStamp = new SimpleDateFormat("yyyyMMddhhmmss").format(new Date());
		
		TakesScreenshot takesScreenshot = (TakesScreenshot) driver;
		File sourceFile = takesScreenshot.getScreenshotAs(OutputType.FILE);
		
		String targetFilePath=System.getProperty("user.dir")+"\\screenshots\\" + testMethodName + "_" + timeStamp + ".png";
		File targetFile=new File(targetFilePath);
		
		sourceFile.renameTo(targetFile);
			
		return targetFilePath;
	}

}
