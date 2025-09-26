FROM ubuntu:24.04

# temporary for troubleshooting
RUN apt-get update && apt-get install -y \
    iputils-ping \
    netcat-traditional \
    net-tools \
    iproute2 \
    vim

# ======== INSTALL ASSETS ========
RUN apt-get update && apt-get install -y  \
    mysql-client \
    gettext-base
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# ======== APPLY SETUP ARTEFACTS ========
ARG VERSION_FOLDER
WORKDIR /zabbix_test
COPY setup_db.sh ${VERSION_FOLDER}/*.sql ./
RUN chmod +x setup_db.sh

ENTRYPOINT ["bash","-c","./setup_db.sh"]
