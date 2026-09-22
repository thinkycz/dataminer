FROM mcr.microsoft.com/playwright:v1.62.1-noble

WORKDIR /opt/dataminer-browser
COPY package.json package-lock.json ./
RUN npm ci --omit=dev --ignore-scripts
COPY resources/scraping/browser-service.mjs resources/scraping/network-proxy.mjs resources/scraping/website-adapter.mjs ./resources/scraping/

USER pwuser
ENV BROWSER_SERVICE_PORT=3210
ENV BROWSER_SERVICE_HOST=0.0.0.0
ENV BROWSER_SERVICE_ALLOW_PRIVATE_BIND=1
EXPOSE 3210
CMD ["node", "resources/scraping/browser-service.mjs"]
