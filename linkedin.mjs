import { chromium } from 'playwright';
import fetch from 'node-fetch';

(async () => {
    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();

    const basePageURL = 'https://www.linkedin.com/jobs-guest/jobs/api/seeMoreJobPostings/search?distance=100&f_TPR=r172800&keywords=web+developer&location=&trk=public_jobs_jobs-search-bar_search-submit&start=50&f_I=&sortBy=DD';//start desde 100 hasta 0
    const apiUrl = 'https://jobs.ajamba.org/wp-json/custom-post-creator/v1/create-post';  
    const products = [];

    

    try {
        await page.goto(basePageURL);

        const pageProducts = await page.$$eval('body li', (results) =>
            results.map((el) => {
                //website
                const pagina = "linkedin";

                const titleElement = el.querySelector('.base-card a.base-card__full-link');
                const title = titleElement?.innerText.trim();
                const link_before = titleElement?.getAttribute('href');

                const link = link_before.split('?')[0];

                const meta_link = link.replace(/^https?:\/\/[^\/]+/, '');

                const jobCompany = el.querySelector('.base-search-card__info .base-search-card__subtitle a');
                const job_company = jobCompany?.innerText.trim();

                const jobLocation = el.querySelector('.base-search-card__info div.base-search-card__metadata span.job-search-card__location');
                const job_location = jobLocation?.innerText.trim();

                const description = title+ '<br>Company: '+job_company+'<br>Location: '+job_location ;

                if (title && link && meta_link && job_company && job_location  && pagina) {
                    return { title, link, meta_link, job_company, job_location, pagina };
                }
                return null;
            }).filter(product => product !== null)
        );

        /*para pruebas*/
        //products.push(...pageProducts);
        // console.log(products);
        // process.exit();
        /*para pruebas*/


        for (let product of pageProducts) {
            try {
                await page.goto(product.link, { waitUntil: 'domcontentloaded' });
                await page.waitForTimeout(3000); 

                const descriptionExists = await page.$('main .show-more-less-html');
                if (descriptionExists) {
                    
                    let descriptionRest = await page.$eval('.show-more-less-html__markup',   el => el.innerHTML.trim() );

                    let descriptionFull = descriptionRest;

                    product.description = descriptionFull;


                    products.push(product);
                } else {
                    console.error(`Descripción no encontrada para ${product.link}`);
                }

            } catch (error) {
                console.error(`Error al obtener la descripción para ${product.link}:`, error);
            }
        }

        

        //prueba completa
        console.log(products);

        //process.exit();

        const postData = {
            posts: products
        };


        fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(postData)
        })
        .then(response => response.json())
        .then(data => console.log(data)) 
        .catch(error => console.error('Error:', error));

    } catch (error) {
        console.error('Error al cargar la página base:', error);
    } finally {
        await browser.close();
    }
})();
