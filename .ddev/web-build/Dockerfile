ARG BASE_IMAGE
FROM $BASE_IMAGE

RUN apt-get update && apt-get install -y \
  libjpeg-progs \
  pngcrush \
  webp \
  imagemagick \
  php-imagick \
  && rm -rf /var/lib/apt/lists/*
