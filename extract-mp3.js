import puppeteer from 'puppeteer';

async function extractMp3Url(zencastrUrl) {
    let browser;
    try {
        // Launch browser with headless mode
        browser = await puppeteer.launch({
            headless: true,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-accelerated-2d-canvas',
                '--no-first-run',
                '--no-zygote',
                '--disable-gpu'
            ]
        });

        const page = await browser.newPage();
        
        // Set user agent to mimic a real browser
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        
        // Navigate to the Zencastr URL
        await page.goto(zencastrUrl, { 
            waitUntil: 'networkidle2',
            timeout: 30000 
        });

        // Wait for the __NEXT_DATA__ script to be present
        await page.waitForSelector('#__NEXT_DATA__', { timeout: 10000 });

        // Extract the JSON data from the script tag
        const nextData = await page.evaluate(() => {
            const script = document.querySelector('#__NEXT_DATA__');
            if (script) {
                try {
                    return JSON.parse(script.textContent);
                } catch (e) {
                    return null;
                }
            }
            return null;
        });

        if (nextData && nextData.props && nextData.props.pageProps && nextData.props.pageProps.episode) {
            const audioUrl = nextData.props.pageProps.episode.audioFile?.url;
            if (audioUrl) {
                return {
                    success: true,
                    audioUrl: audioUrl,
                    message: 'MP3 URL extracted successfully!'
                };
            } else {
                return {
                    success: false,
                    message: 'No audioFile URL found in the episode data.'
                };
            }
        } else {
            return {
                success: false,
                message: 'Could not find episode data in __NEXT_DATA__ script.'
            };
        }

    } catch (error) {
        return {
            success: false,
            message: 'Error extracting MP3 URL: ' + error.message
        };
    } finally {
        if (browser) {
            await browser.close();
        }
    }
}

// Get URL from command line arguments
const zencastrUrl = process.argv[2];

if (!zencastrUrl) {
    console.log(JSON.stringify({
        success: false,
        message: 'No URL provided'
    }));
    process.exit(1);
}

// Extract MP3 URL and output as JSON
extractMp3Url(zencastrUrl).then(result => {
    console.log(JSON.stringify(result));
}).catch(error => {
    console.log(JSON.stringify({
        success: false,
        message: 'Unexpected error: ' + error.message
    }));
    process.exit(1);
});
