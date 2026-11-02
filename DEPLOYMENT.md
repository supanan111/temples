# 🚀 คู่มือการ Deploy ระบบ ThumWadThaiHub

โปรเจกต์นี้รองรับการ Deploy ทั้งบน **Render** (แนะนำที่สุดสำหรับ PHP) และ **Vercel** รวมถึงรองรับฐานข้อมูล **Neon (PostgreSQL)** หรือ **Cloud MySQL**

---

## 📋 สารบัญ
1. [การเตรียมฐานข้อมูล (Neon หรือ Cloud MySQL)](#1-การเตรียมฐานข้อมูล)
2. [ตัวเลือกที่ 1: Deploy บน Render (แนะนำสำหรับ PHP)](#2-deploy-บน-render-แนะนำ)
3. [ตัวเลือกที่ 2: Deploy บน Vercel](#3-deploy-บน-vercel)
4. [การตั้งค่า Environment Variables](#4-การตั้งค่า-environment-variables)

---

## 1. การเตรียมฐานข้อมูล

### กรณีใช้ Neon Database (PostgreSQL)
1. สมัครใช้งานที่ [https://neon.tech](https://neon.tech) และสร้างโปรเจกต์ใหม่ (เลือก Region: `ap-southeast-1` Singapore)
2. ไปที่เมนู **SQL Editor** ใน Neon Console
3. คัดลอกคำสั่ง SQL จากไฟล์ [`database/schema_postgres_neon.sql`](database/schema_postgres_neon.sql) ไปวางแล้วกด **Run**
4. คัดลอก **Connection String** (เช่น `postgres://user:password@ep-xyz.ap-southeast-1.aws.neon.tech/neondb?sslmode=require`) เพื่อนำไปใส่ใน `DATABASE_URL`

### กรณีใช้ Cloud MySQL (Aiven / PlanetScale / Clever Cloud / InfinityFree)
1. เข้า phpMyAdmin หรือ SQL Console ของผู้ให้บริการ
2. นำไฟล์ [`database/schema_mysql.sql`](database/schema_mysql.sql) หรือ [`database/migration_v2.sql`](database/migration_v2.sql) ไปรัน
3. นำ Host, Database, User, Password ไปตั้งค่าใน Environment Variables

---

## 2. Deploy บน Render (แนะนำ) ⭐

Render รองรับ Docker Container ทำให้ระบบ PHP, Apache และการอัปโหลดไฟล์ทำงานได้สมบูรณ์ 100%

### ขั้นตอน:
1. เข้าไปที่ [https://render.com](https://render.com) แล้วล็อกอินด้วย GitHub
2. กดปุ่ม **New +** > เลือก **Web Service**
3. เลือก Repository GitHub ของคุณที่เพิ่ง Push ขึ้นไป
4. Render จะตรวจพบ [`Dockerfile`](Dockerfile) อัตโนมัติ:
   - **Environment:** Docker
   - **Region:** Singapore
   - **Instance Type:** Free
5. เลื่อนลงมาที่หัวข้อ **Environment Variables** แล้วกดเพิ่ม:
   - **หากใช้ Neon (PostgreSQL):**
     - `DATABASE_URL`: วาง Connection String จาก Neon
   - **หากใช้ MySQL:**
     - `DB_DRIVER`: `mysql`
     - `DB_HOST`: โฮสต์ MySQL
     - `DB_PORT`: `3306`
     - `DB_NAME`: ชื่อฐานข้อมูล
     - `DB_USER`: ชื่อผู้ใช้
     - `DB_PASS`: รหัสผ่าน
6. กด **Create Web Service** — Render จะทำการ Build และ Deploy ให้อัตโนมัติภายใน 2-3 นาที

---

## 3. Deploy บน Vercel

Vercel เป็น Serverless Platform ทำงานผ่าน runtime `vercel-php` ที่กำหนดไว้ใน [`vercel.json`](vercel.json)

### ขั้นตอน:
1. ไปที่ [https://vercel.com](https://vercel.com) แล้วล็อกอินด้วย GitHub
2. กด **Add New...** > เลือก **Project**
3. Import Repository ของคุณ
4. ในส่วน **Environment Variables** เพิ่ม:
   - `DATABASE_URL` หรือตัวแปร `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
5. กด **Deploy**

> **หมายเหตุสำหรับ Vercel:** Vercel มี Read-Only Filesystem ใน Serverless Container (ไฟล์ที่อัปโหลดผ่านเว็บจะไม่คงอยู่ถาวรหลัง Function รีสตาร์ท) หากระบบต้องมีการอัปโหลดสลิปหรือรูปกิจกรรม แนะนำให้ใช้ **Render** เป็นหลัก

---

## 4. โครงสร้างไฟล์สำหรับ Deployment

```text
├── Dockerfile                   # Docker build สำหรับ Render (PHP 8.2 + Apache + MySQL + PgSQL)
├── render.yaml                  # Blueprint config สำหรับ Render
├── vercel.json                  # Serverless config สำหรับ Vercel
├── .env.example                 # ตัวอย่างค่า Environment Variables
├── .htaccess                    # ป้องกันไฟล์ Sensitive (.env, .sql, config)
├── .dockerignore                # ไฟล์ที่ไม่นำเข้า Docker Image
├── .gitignore                   # กรองไฟล์ที่ไม่ขึ้น Git (.env, upload temp)
├── db_config.php                # เชื่อมต่อ DB แบบอัตโนมัติ (รองรับ DATABASE_URL, Env, และ Fallback)
└── database/
    ├── schema_mysql.sql         # สกีมาสำหรับ MySQL ครบทุกตาราง
    ├── schema_postgres_neon.sql # สกีมาสำหรับ Neon (PostgreSQL) ครบทุกตาราง
    └── migration_v2.sql         # สคริปต์ Migration v2.0
```
