FROM php:8.2-apache

# ติดตั้ง System dependencies และ PHP Extensions สำหรับ MySQL, PostgreSQL (Neon), GD, Zip
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        pdo_pgsql \
        mysqli \
        gd \
        zip \
        calendar \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# เปิดใช้งาน Apache Rewrite, Headers, Expires, Deflate Modules เพื่อความเร็วและแคชรูปภาพ
RUN a2enmod rewrite headers expires deflate

# คัดลอกโค้ดโปรเจกต์ทั้งหมดเข้าสู่ DocumentRoot
COPY . /var/www/html/

# สร้างโฟลเดอร์สำหรับอัปโหลดและแคช พร้อมกำหนดสิทธิ์ให้ Apache (www-data)
RUN mkdir -p /var/www/html/uploads/events /var/www/html/uploads/slips /var/www/html/uploads/qrcodes /var/www/html/donation_uploads /var/www/html/cache \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/uploads /var/www/html/donation_uploads /var/www/html/cache

# กำหนดค่า PHP
RUN echo "upload_max_filesize = 10M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 12M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/uploads.ini

EXPOSE 80

CMD ["apache2-foreground"]

