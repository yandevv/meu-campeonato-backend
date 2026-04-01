FROM nginx:alpine

# Copy public directory directly from build context
COPY public /var/www/html/public

COPY docker/nginx/nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 8080
