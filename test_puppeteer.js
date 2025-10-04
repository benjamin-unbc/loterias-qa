const puppeteer = require('puppeteer');

async function testPuppeteer() {
    let browser;
    try {
        console.log('Iniciando Puppeteer...');
        browser = await puppeteer.launch({
            headless: true,
            args: ['--no-sandbox', '--disable-setuid-sandbox']
        });
        
        const page = await browser.newPage();
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        
        console.log('Navegando a la página...');
        await page.goto('https://www.notitimba.com/lots/vcu/', { waitUntil: 'networkidle2', timeout: 30000 });
        
        console.log('Esperando a que cargue...');
        await page.waitForTimeout(5000);
        
        // Buscar todos los enlaces que contengan "Vista Completa"
        const vistaCompletaLinks = await page.evaluate(() => {
            const links = Array.from(document.querySelectorAll('a'));
            return links
                .filter(link => link.textContent.toLowerCase().includes('vista completa'))
                .map(link => ({
                    text: link.textContent.trim(),
                    href: link.href,
                    id: link.id,
                    className: link.className
                }));
        });
        
        console.log('Enlaces encontrados:', JSON.stringify(vistaCompletaLinks, null, 2));
        
        // Si encontramos enlaces, hacer clic en el primero
        if (vistaCompletaLinks.length > 0) {
            console.log('Haciendo clic en Vista Completa...');
            await page.click('a:contains("Vista Completa")');
            await page.waitForTimeout(5000);
        }
        
        // Extraer el contenido
        const content = await page.content();
        const title = await page.title();
        
        console.log('Título:', title);
        console.log('Contenido tiene', content.length, 'caracteres');
        
        // Buscar tablas
        const tableCount = await page.evaluate(() => {
            return document.querySelectorAll('table').length;
        });
        
        console.log('Tablas encontradas:', tableCount);
        
    } catch (error) {
        console.error('Error:', error.message);
    } finally {
        if (browser) {
            await browser.close();
        }
    }
}

testPuppeteer();
