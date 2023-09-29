FROM nginx:alpine
ADD nginx/default.conf /etc/nginx/conf.d

RUN addgroup --gid 1000 mygroup
RUN adduser  --system --no-create-home --disabled-password --uid 1000 --ingroup mygroup myuser

WORKDIR /var/www/html

RUN chown -R myuser:mygroup .

USER myuser