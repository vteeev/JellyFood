FROM postgres

ENV POSTGRES_PASSWORD docker
ENV POSTGRES_DB db
ENV POSTGRES_USER docker

# Kopiuj init.sql do folderu gdzie PostgreSQL automatycznie go wykonuje
COPY docker/db/init.sql /docker-entrypoint-initdb.d/