# 🏛️ ThumWadThaiHub — เอกสารโครงสร้างระบบฉบับสมบูรณ์
> **System Architecture, Process Flowcharts, Database Schema & Data Dictionary**  
> *โปรเจกต์: ระบบศูนย์รวมข้อมูลและบริหารจัดการวัดไทยออนไลน์ (ThumWadThaiHub)*

---

## 📋 สารบัญ
1. [System Architecture (สถาปัตยกรรมระบบ)](#1-system-architecture-สถาปัตยกรรมระบบ)
   - [1.1 ภาพรวมสถาปัตยกรรม (High-Level Architecture)](#11-ภาพรวมสถาปัตยกรรม-high-level-architecture)
   - [1.2 โครงสร้างเทคโนโลยี (Technology Stack)](#12-โครงสร้างเทคโนโลยี-technology-stack)
   - [1.3 การควบคุมสิทธิ์การเข้าถึง (Role-Based Access Control)](#13-การควบคุมสิทธิ์การเข้าถึง-role-based-access-control)
2. [Process Flowcharts (แผนผังกระบวนการทำงาน)](#2-process-flowcharts-แผนผังกระบวนการทำงาน)
   - [2.1 การเข้าสู่ระบบและการแยกสิทธิ์ (Authentication & Role Dispatch Flow)](#21-การเข้าสู่ระบบและการแยกสิทธิ์-authentication--role-dispatch-flow)
   - [2.2 วงจรชีวิตการจองสิ่งของและสถานที่ (Booking Lifecycle Flow)](#22-วงจรชีวิตการจองสิ่งของและสถานที่-booking-lifecycle-flow)
   - [2.3 การบริจาคเงินและตรวจสอบสลิป (Online Donation & Slip Verification Flow)](#23-การบริจาคเงินและตรวจสอบสลิป-online-donation--slip-verification-flow)
   - [2.4 ระบบแจ้งเตือน (In-App Notification Dispatch Flow)](#24-ระบบแจ้งเตือน-in-app-notification-dispatch-flow)
3. [Database Schema & ER Diagram (แผนภาพโครงสร้างฐานข้อมูล)](#3-database-schema--er-diagram-แผนภาพโครงสร้างฐานข้อมูล)
   - [3.1 Entity Relationship Diagram (ERD)](#31-entity-relationship-diagram-erd)
   - [3.2 ความสัมพันธ์ระหว่างตาราง (Relationship Summary)](#32-ความสัมพันธ์ระหว่างตาราง-relationship-summary)
4. [Data Dictionary (พจนานุกรมข้อมูลฉบับสมบูรณ์)](#4-data-dictionary-พจนานุกรมข้อมูลฉบับสมบูรณ์)
   - [4.1 ตาราง `temples` (ข้อมูลวัด)](#41-ตาราง-temples-ข้อมูลวัด)
   - [4.2 ตาราง `temple_sections` (ข้อมูลหมวดหมู่ย่อยของวัด)](#42-ตาราง-temple_sections-ข้อมูลหมวดหมู่ย่อยของวัด)
   - [4.3 ตาราง `users` (ผู้ใช้งานและผู้ดูแลระบบ)](#43-ตาราง-users-ผู้ใช้งานและผู้ดูแลระบบ)
   - [4.4 ตาราง `bookable_items` (รายการบริการ/สิ่งของที่เปิดให้จอง)](#44-ตาราง-bookable_items-รายการบริการสิ่งของที่เปิดให้จอง)
   - [4.5 ตาราง `bookings` (ข้อมูลรายการจอง)](#45-ตาราง-bookings-ข้อมูลรายการจอง)
   - [4.6 ตาราง `donations` (ข้อมูลการบริจาคและทำบุญ)](#46-ตาราง-donations-ข้อมูลการบริจาคและทำบุญ)
   - [4.7 ตาราง `events` (กิจกรรมและงานประจำปี)](#47-ตาราง-events-กิจกรรมและงานประจำปี)
   - [4.8 ตาราง `news` (ข่าวสารประชาสัมพันธ์)](#48-ตาราง-news-ข่าวสารประชาสัมพันธ์)
   - [4.9 ตาราง `notifications` (ระบบแจ้งเตือน)](#49-ตาราง-notifications-ระบบแจ้งเตือน)

---

# 1. System Architecture (สถาปัตยกรรมระบบ)

### 1.1 ภาพรวมสถาปัตยกรรม (High-Level Architecture)

ระบบออกแบบตามโครงสร้าง **Multi-Tier Web Architecture** รองรับสภาพแวดล้อมตั้งแต่ Local Server (XAMPP), Dockerized Web Service (Render) จนถึง Serverless (Vercel) เชื่อมโยงกับฐานข้อมูล Dual-Engine (MySQL / Neon PostgreSQL)

```mermaid
flowchart TB
    subgraph ClientLayer["1. Client Layer (ผู้ใช้งาน)"]
        Guest["ผู้เยี่ยมชมทั่วไป (Guest)"]
        User["สมาชิก / พุทธศาสนิกชน (Member)"]
        Admin["ผู้ดูแลวัด (Temple Admin)"]
        SuperAdmin["ผู้ดูแลระบบหลัก (Super Admin)"]
    end

    subgraph GatewayLayer["2. Web Gateway Layer"]
        LB["Web Server (Apache / Render Docker / Vercel Edge)"]
        Routing[".htaccess & URL Security Rewrite"]
        Assets["Static Assets (/assets, CSS, JS, Images)"]
    end

    subgraph AppLayer["3. Application & Business Logic Layer (PHP 8.2)"]
        AuthModule["Authentication & RBAC Engine<br/>(login, register, session_regenerate)"]
        TempleModule["Temple Information & Content Engine<br/>(temple_page, sections, search)"]
        BookingModule["Booking & Capacity Engine<br/>(booking_process, manage_bookings, cancel)"]
        DonationModule["Donation & Slip Engine<br/>(donate_qr, PromptPay generator, slip upload)"]
        NotifModule["Notification Dispatcher<br/>(notify.php, polling / unread counter)"]
    end

    subgraph DataAccessLayer["4. Data Access Layer (PDO Abstraction)"]
        DBConfig["db_config.php<br/>- Auto DATABASE_URL parser<br/>- Multi-driver fallback (MySQL/PostgreSQL)"]
    end

    subgraph PersistenceLayer["5. Persistence & Storage Layer"]
        subgraph DatabaseEngines["Database Engines"]
            MySQL[("MySQL / MariaDB<br/>Local / Cloud MySQL")]
            PostgreSQL[("PostgreSQL (Neon.tech)<br/>Serverless Cloud DB")]
        end
        subgraph FileStorage["File Storage Engine"]
            UploadsDir["/uploads (รูปหน้าปกวัด, ภาพกิจกรรม, ข่าวสาร)"]
            DonationSlipsDir["/donation_uploads (หลักฐานสลิปการโอนเงิน)"]
        end
    end

    ClientLayer --> LB
    LB --> Routing
    Routing --> Presentation["Presentation Engine"]
    Presentation --> AppLayer
    AppLayer --> DataAccessLayer
    DataAccessLayer --> DBConfig
    DBConfig --> DatabaseEngines
    AppLayer --> FileStorage
```

### 1.2 โครงสร้างเทคโนโลยี (Technology Stack)
- **Frontend / Client:** HTML5, CSS3, JavaScript (Vanilla JS), Bootstrap / Custom Responsive Styles, FontAwesome Icons
- **Backend / Server:** PHP 8.2 (Procedural + Prepared Statements PDO), Apache HTTP Server (`mod_rewrite`, `mod_headers`)
- **Database Engine:** MySQL 8.x / MariaDB 10.x และ PostgreSQL 15+ (ผ่าน Neon Serverless Driver)
- **File & Media Handling:** PHP GD Library สำหรับตรวจสอบและจัดการไฟล์ภาพอัปโหลด
- **Payment & Verification:** PromptPay QR Code Payload Generator สำหรับบริจาคเงินแบบ Real-time
- **Deployment Platform:** Render (Docker Web Service) และ Vercel (Serverless Functions via `vercel-php`)

### 1.3 การควบคุมสิทธิ์การเข้าถึง (Role-Based Access Control)

| บทบาท (Role) | สิทธิ์การเข้าถึงและการทำงานหลัก | สิทธิ์จัดการข้อมูล | หน้าจอควบคุมหลัก |
| :--- | :--- | :--- | :--- |
| **Guest** | ค้นหาวัดตามภาค/จังหวัด, ดูรายละเอียดวัด, ดูกิจกรรม/ข่าว, สแกน QR บริจาคเงิน | อ่านอย่างเดียว (Read Only) | `index.php`, `temple_page.php`, `donate.php` |
| **Member** (`user`) | จองศาลา/อุปกรณ์/พระสงฆ์/แผงค้า, อัปโหลดสลิปบริจาค, เช็คสถานะ, กดยกเลิกการจอง, รับแจ้งเตือน | บันทึกคำขอและจัดการเฉพาะรายการของตนเอง | `booking.php`, `my_bookings.php`, `check_status.php` |
| **Temple Admin** (`admin`) | บริหารวัดที่สังกัด (`temple_id`), จัดการสิ่งของให้จอง, อนุมัติ/ปฏิเสธการจอง, ตรวจสอบสลิปบริจาค, ลงข่าวและกิจกรรม | จัดการข้อมูลทั้งหมดเฉพาะในวัดของตนเอง | `admin_dashboard.php`, `manage_bookings.php`, `manage_donations.php` |
| **Super Admin** (`superadmin`) | จัดการรายชื่อวัดทั้งหมด, แต่งตั้ง/ลบบัญชีผู้ดูแลวัด, สลับสิทธิ์เข้าดูวัด (`switch_temple`), ดูสถิติรวมทั้งระบบ | สิทธิ์ระดับผู้ดูแลสูงสุด (Full System Access) | `superadmin_dashboard.php`, `manage_temples.php`, `manage_users.php` |

---

# 2. Process Flowcharts (แผนผังกระบวนการทำงาน)

### 2.1 การเข้าสู่ระบบและการแยกสิทธิ์ (Authentication & Role Dispatch Flow)

```mermaid
flowchart TD
    Start(["เริ่มต้น"]) --> LoginInput["ผู้ใช้กรอก Username และ Password"]
    LoginInput --> QueryUser["ค้นหาข้อมูลผู้ใช้ในตาราง users"]
    QueryUser --> CheckUser{"พบบัญชีผู้ใช้หรือไม่?"}
    
    CheckUser -- "ไม่พบ" --> RedirectFail["แจ้งเตือน: ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง"]
    RedirectFail --> LoginInput
    
    CheckUser -- "พบ" --> VerifyPassword{"ตรวจสอบรหัสผ่าน<br/>(password_verify / plain)"}
    VerifyPassword -- "รหัสไม่ถูกต้อง" --> RedirectFail
    
    VerifyPassword -- "รหัสถูกต้อง" --> GenSession["สร้าง Session (user_id, role, temple_id)<br/>และ session_regenerate_id()"]
    GenSession --> CheckRole{"ตรวจสอบสิทธิ์ (Role)"}
    
    CheckRole -- "superadmin" --> SuperDash["ไปยังหน้า superadmin_dashboard.php"]
    CheckRole -- "admin" --> AdminDash["ไปยังหน้า admin_dashboard.php"]
    CheckRole -- "user" --> CheckNext{"มี URL ปลายทางเดิม<br/>(next parameter)?"}
    
    CheckNext -- "มี" --> GoNext["ส่งกลับไปยังหน้าที่ต้องการ (เช่น booking_form.php)"]
    CheckNext -- "ไม่มี" --> GoIndex["ส่งไปยังหน้าหลัก index.php"]
    
    SuperDash --> Finish(["สิ้นสุด"])
    AdminDash --> Finish
    GoNext --> Finish
    GoIndex --> Finish
```

---

### 2.2 วงจรชีวิตการจองสิ่งของและสถานที่ (Booking Lifecycle Flow)

```mermaid
flowchart TD
    Start(["เริ่มต้นการจอง"]) --> SelectItem["ผู้ใช้เลือกวัด และเลือกสิ่งของ/ศาลา/บริการ"]
    SelectItem --> CheckLogin{"เข้าสู่ระบบแล้วหรือยัง?"}
    
    CheckLogin -- "ยังไม่ได้เข้าสู่ระบบ" --> PromptLogin["ส่งไปหน้า login.php พร้อมแนบ return URL"]
    PromptLogin --> SelectItem
    
    CheckLogin -- "เข้าสู่ระบบแล้ว" --> FillForm["กรอกวันที่, จำนวนที่ต้องการ, ข้อมูลผู้ติดต่อ"]
    FillForm --> SubmitBooking["ส่งข้อมูลเข้า booking_process.php"]
    
    SubmitBooking --> CheckCapacity{"ตรวจสอบคิว/จำนวนคงเหลือ<br/>ณ วันที่เลือก"}
    CheckCapacity -- "คิวเต็ม / เกินโควตา" --> AlertFull["แจ้งเตือน: รายการนี้ถูกจองเต็มแล้วในวันดังกล่าว"]
    AlertFull --> FillForm
    
    CheckCapacity -- "ว่าง / สามารถจองได้" --> SaveBooking["บันทึกรายการลงตาราง bookings<br/>(status = 'pending')"]
    SaveBooking --> CreateAdminNotif["สร้าง Notification แจ้งเตือนไปยัง Admin วัด"]
    SaveBooking --> ShowStatus["แสดงผลการจองในหน้า my_bookings.php"]
    
    ShowStatus --> DecisionFork{"การดำเนินการต่อ"}
    
    DecisionFork -- "Admin พิจารณา" --> AdminAction{"ผลการพิจารณาของ Admin"}
    AdminAction -- "อนุมัติ" --> UpdateApproved["ปรับ status = 'approved'"]
    AdminAction -- "ปฏิเสธ" --> UpdateRejected["ปรับ status = 'rejected'<br/>ระบุ admin_note"]
    
    DecisionFork -- "ผู้ใช้ขอยกเลิกเอง" --> UserCancel{"ตรวจสอบสถานะปัจจุบัน"}
    UserCancel -- "สถานะยังยกเลิกได้" --> UpdateCancelled["ปรับ status = 'cancelled'<br/>cancelled_by = 'user'<br/>cancelled_at = NOW()"]
    UserCancel -- "ไม่สามารถยกเลิกได้" --> ShowStatus
    
    UpdateApproved --> NotifyUser["ส่ง Notification แจ้งเตือนสมาชิกผู้จอง"]
    UpdateRejected --> NotifyUser
    UpdateCancelled --> NotifyAdmin["ส่ง Notification แจ้งเตือนแอดมินวัด"]
    
    NotifyUser --> End(["สิ้นสุดกระบวนการ"])
    NotifyAdmin --> End
```

---

### 2.3 การบริจาคเงินและตรวจสอบสลิป (Online Donation & Slip Verification Flow)

```mermaid
flowchart TD
    Start(["เริ่มต้นทำบุญ"]) --> SelectTemple["ผู้ใช้เลือกวัด และระบุจำนวนเงินบริจาค"]
    SelectTemple --> ChooseTax{"ต้องการใบอนุโมทนาบัตรหรือไม่?"}
    ChooseTax -- "ต้องการ" --> FillDonorInfo["กรอกชื่อ-นามสกุล และข้อมูลผู้บริจาค"]
    ChooseTax -- "ไม่ต้องการ" --> AnonymousCheck{"ประสงค์ออกนามหรือไม่?"}
    AnonymousCheck -- "ไม่ประสงค์ออกนาม" --> SetAnon["ระบบบันทึกชื่อเป็น 'ผู้ไม่ประสงค์ออกนาม'"]
    AnonymousCheck -- "ประสงค์ออกนาม" --> FillDonorInfo
    
    SetAnon --> GenQR["สร้าง PromptPay QR Code ตามยอดเงินและ PromptPay ID ของวัด"]
    FillDonorInfo --> GenQR
    
    GenQR --> UserPay["ผู้ใช้สแกนชำระเงินผ่าน Mobile Banking"]
    UserPay --> UploadSlip["อัปโหลดภาพหลักฐานการโอน (สลิป) เข้าสู่ระบบ"]
    
    UploadSlip --> CheckFile{"ตรวจสอบไฟล์ภาพ<br/>(นามสกุล JPG/PNG/WEBP, ขนาดไม่เกินกำหนด)"}
    CheckFile -- "ไม่ผ่าน" --> AlertFileError["แจ้งเตือน: ไฟล์ไม่ถูกต้องหรือขนาดเกินกำหนด"]
    AlertFileError --> UploadSlip
    
    CheckFile -- "ผ่าน" --> SaveDonation["บันทึกข้อมูลลงตาราง donations<br/>(status = 'pending', slip_image = path)"]
    SaveDonation --> NotifyAdmin["สร้าง Notification แจ้งเตือน Admin วัด"]
    
    NotifyAdmin --> AdminReview{"Admin วัดตรวจสอบหลักฐานสลิป<br/>(manage_donations.php)"}
    AdminReview -- "ยอดเงินและสลิปถูกต้อง" --> MarkSuccess["ปรับ status = 'completed'<br/>ออกหมายเลข receipt_no"]
    AdminReview -- "สลิปไม่ถูกต้อง / ซ้ำ" --> MarkFailed["ปรับ status = 'failed'"]
    
    MarkSuccess --> Done(["สิ้นสุดกระบวนการ"])
    MarkFailed --> Done
```

---

### 2.4 ระบบแจ้งเตือน (In-App Notification Dispatch Flow)

```mermaid
flowchart TD
    Trigger(["เหตุการณ์ในระบบ (Event Trigger)"]) --> IdentifyTarget["ระบุประเภทและผู้รับการแจ้งเตือน (user_id)"]
    
    IdentifyTarget --> EventBranch{"ประเภทเหตุการณ์"}
    EventBranch -- "มีการจองใหม่" --> Msg1["type = 'new_booking' ส่งหา Admin วัด"]
    EventBranch -- "การจองได้รับอนุมัติ" --> Msg2["type = 'booking_approved' ส่งหา Member ผู้จอง"]
    EventBranch -- "การจองถูกปฏิเสธ" --> Msg3["type = 'booking_rejected' ส่งหา Member ผู้จอง"]
    EventBranch -- "การจองถูกยกเลิก" --> Msg4["type = 'booking_cancelled' ส่งหา Admin หรือ Member"]
    EventBranch -- "มีการบริจาคเงินใหม่" --> Msg5["type = 'new_donation' ส่งหา Admin วัด"]
    
    Msg1 --> InsertNotif["บันทึกลงตาราง notifications<br/>(user_id, ref_id, ref_table, message, is_read=0)"]
    Msg2 --> InsertNotif
    Msg3 --> InsertNotif
    Msg4 --> InsertNotif
    Msg5 --> InsertNotif
    
    InsertNotif --> ClientFetch["Client ฝั่งหน้าบ้าน Polling ผ่าน get_notifications.php"]
    ClientFetch --> UpdateUI["แสดง Badge ตัวเลขแจ้งเตือนที่ Navbar และ Dropdown รายการ"]
    UpdateUI --> UserClick["ผู้ใช้กดอ่านการแจ้งเตือน"]
    UserClick --> MarkRead["ปรับ is_read = 1"]
    MarkRead --> EndNotif(["เสร็จสิ้น"])
```

---

# 3. Database Schema & ER Diagram (แผนภาพโครงสร้างฐานข้อมูล)

### 3.1 Entity Relationship Diagram (ERD)

```mermaid
erDiagram
    temples ||--o{ temple_sections : "has many"
    temples ||--o{ users : "employs (admins)"
    temples ||--o{ bookable_items : "offers"
    temples ||--o{ bookings : "receives"
    temples ||--o{ donations : "receives"
    temples ||--o{ events : "organizes"
    temples ||--o{ news : "publishes"

    users ||--o{ bookings : "submits"
    users ||--o{ notifications : "receives"

    bookable_items ||--o{ bookings : "booked through"

    temples {
        int id PK "รหัสประจำตัววัด"
        varchar name "ชื่อวัด"
        varchar province "จังหวัด"
        varchar district "อำเภอ/เขต"
        text address "ที่อยู่"
        text description "รายละเอียด"
        text tags "คีย์เวิร์ดค้นหา"
        decimal latitude "พิกัดละติจูด"
        decimal longitude "พิกัดลองจิจูด"
        varchar image "รูปหน้าปก"
        varchar promptpay_id "เลขพร้อมเพย์"
        varchar badge "ป้ายกำกับ"
        timestamp created_at "วันเวลาที่สร้าง"
    }

    temple_sections {
        int id PK "รหัสหัวข้อย่อย"
        int temple_id FK "รหัสวัด"
        varchar title "หัวข้อ"
        text content "เนื้อหา"
        timestamp created_at "วันเวลาที่สร้าง"
    }

    users {
        int id PK "รหัสผู้ใช้"
        varchar username UK "ชื่อบัญชี"
        varchar password "รหัสผ่านแฮช"
        varchar full_name "ชื่อ-นามสกุล"
        varchar role "สิทธิ์ใช้งาน"
        int temple_id FK "รหัสวัดที่สังกัด"
        varchar profile_image "รูปโปรไฟล์"
        timestamp created_at "วันเวลาที่สมัคร"
    }

    bookable_items {
        int id PK "รหัสรายการสิ่งของ"
        int temple_id FK "รหัสวัด"
        varchar item_name "ชื่อสิ่งของ/บริการ"
        varchar item_type "ประเภทบริการ"
        text description "รายละเอียด"
        decimal price "ราคา/ค่าบำรุง"
        int total_qty "จำนวนรวมที่มี"
        enum is_active "สถานะเปิดจอง"
        timestamp created_at "วันเวลาที่เพิ่ม"
    }

    bookings {
        int id PK "รหัสการจอง"
        int temple_id FK "รหัสวัด"
        int item_id FK "รหัสสิ่งของที่จอง"
        int user_id FK "รหัสผู้จอง"
        date booking_date "วันที่ต้องการใช้"
        varchar stall_no "เลขแผงค้า"
        int qty_booked "จำนวนที่จอง"
        enum status "สถานะการจอง"
        varchar contact_name "ชื่อผู้ติดต่อ"
        varchar contact_phone "เบอร์โทรผู้ติดต่อ"
        text admin_note "หมายเหตุแอดมิน"
        enum cancelled_by "ผู้กดยกเลิก"
        datetime cancelled_at "วันเวลาที่ยกเลิก"
        timestamp created_at "วันเวลาที่ทำรายการ"
    }

    donations {
        int id PK "รหัสการบริจาค"
        int temple_id FK "รหัสวัด"
        decimal amount "ยอดเงินบริจาค"
        varchar donor_name "ชื่อผู้บริจาค"
        tinyint anonymous "สถานะไม่ประสงค์ออกนาม"
        enum tax_deduction "ขอลดหย่อนภาษี"
        enum status "สถานะการตรวจสอบ"
        varchar slip_image "ไฟล์สลิป"
        varchar receipt_no "เลขที่ใบเสร็จ"
        timestamp created_at "วันเวลาที่ทำรายการ"
    }

    events {
        int id PK "รหัสกิจกรรม"
        int temple_id FK "รหัสวัด"
        varchar event_name "ชื่อกิจกรรม"
        text description "รายละเอียด"
        varchar event_image "รูปภาพกิจกรรม"
        date event_date "วันที่จัดงาน"
        time event_time "เวลาจัดงาน"
        timestamp created_at "วันเวลาที่เพิ่ม"
    }

    news {
        int id PK "รหัสข่าว"
        int temple_id FK "รหัสวัด"
        varchar title "หัวข้อข่าว"
        text content "เนื้อหาข่าว"
        varchar image "รูปภาพข่าว"
        timestamp created_at "วันเวลาที่เผยแพร่"
    }

    notifications {
        int id PK "รหัสการแจ้งเตือน"
        int user_id FK "รหัสผู้รับแจ้งเตือน"
        varchar type "ประเภทแจ้งเตือน"
        int ref_id "รหัสอ้างอิงตารางเป้าหมาย"
        varchar ref_table "ชื่อตารางอ้างอิง"
        text message "ข้อความแจ้งเตือน"
        tinyint is_read "สถานะการเปิดอ่าน"
        timestamp created_at "วันเวลาที่ส่ง"
    }
```

### 3.2 ความสัมพันธ์ระหว่างตาราง (Relationship Summary)
- `temples` เป็น Entity ศูนย์กลาง มีความสัมพันธ์แบบ **1 ต่อ หลาย (1:N)** ไปยัง `temple_sections`, `users` (เฉพาะผู้ดูแลวัด), `bookable_items`, `bookings`, `donations`, `events`, และ `news`
- `users` มีความสัมพันธ์แบบ **1 ต่อ หลาย (1:N)** ไปยัง `bookings` (รายการจองของสมาชิก) และ `notifications` (กล่องข้อความแจ้งเตือน)
- `bookable_items` มีความสัมพันธ์แบบ **1 ต่อ หลาย (1:N)** ไปยัง `bookings` (ประวัติการถูกจองของแต่ละสิ่งของ)

---

# 4. Data Dictionary (พจนานุกรมข้อมูลฉบับสมบูรณ์)

### 4.1 ตาราง `temples` (ข้อมูลวัด)
> ตารางจัดเก็บข้อมูลพื้นฐาน ข้อมูลติดต่อ พิกัดภูมิศาสตร์ และหมายเลขพร้อมเพย์ของแต่ละวัด

| ลำดับ | ชื่อฟิลด์ (Field) | ชนิดข้อมูล (Data Type) | Nullable | คีย์ (Key) | ค่าเริ่มต้น (Default) | คำอธิบายความหมาย (Description) |
| :---: | :--- | :--- | :---: | :---: | :---: | :--- |
| 1 | `id` | `INT` | NO | PK | Auto Increment | รหัสประจำตัววัด (Primary Key) |
| 2 | `name` | `VARCHAR(150)` | NO | - | - | ชื่อทางการของวัด |
| 3 | `province` | `VARCHAR(100)` | YES | Index | NULL | จังหวัดที่ตั้งของวัด |
| 4 | `district` | `VARCHAR(100)` | YES | - | NULL | อำเภอ หรือ เขต ที่ตั้ง |
| 5 | `address` | `TEXT` | YES | - | NULL | ที่อยู่ ตำบล ถนน รหัสไปรษณีย์ |
| 6 | `description` | `TEXT` | YES | - | NULL | รายละเอียด ประวัติความเป็นมา ความสำคัญ |
| 7 | `tags` | `TEXT` | YES | - | NULL | คีย์เวิร์ดช่วยค้นหา คั่นด้วย comma (เช่น พระพุทธชินราช, ริมน้ำ) |
| 8 | `latitude` | `DECIMAL(10,8)` | YES | - | NULL | ค่าพิกัดละติจูด (Latitude) สำหรับหมุดแผนที่ |
| 9 | `longitude` | `DECIMAL(11,8)` | YES | - | NULL | ค่าพิกัดลองจิจูด (Longitude) สำหรับหมุดแผนที่ |
| 10 | `image` | `VARCHAR(255)` | YES | - | NULL | ชื่อไฟล์หรือที่อยู่ภาพหน้าปกวัด (`uploads/...`) |
| 11 | `promptpay_id` | `VARCHAR(13)` | YES | - | NULL | เบอร์โทรศัพท์ 10 หลัก หรือเลขบัตรนิติบุคคล 13 หลักสำหรับรับเงิน |
| 12 | `badge` | `VARCHAR(50)` | YES | - | NULL | ป้ายกำกับพิเศษ (เช่น 'วัดอารามหลวง', 'วัดยอดนิยม') |
| 13 | `created_at` | `TIMESTAMP` | YES | - | `CURRENT_TIMESTAMP` | วันและเวลาที่บันทึกข้อมูลวัดเข้าสู่ระบบ |

---

### 4.2 ตาราง `temple_sections` (ข้อมูลหมวดหมู่ย่อยของวัด)
> ตารางเก็บเนื้อหาย่อยและส่วนประกอบเสริมในหน้าวัด เช่น พระประธาน, จุดเช็คอิน, ประวัติเจ้าอาวาส

| ลำดับ | ชื่อฟิลด์ (Field) | ชนิดข้อมูล (Data Type) | Nullable | คีย์ (Key) | ค่าเริ่มต้น (Default) | คำอธิบายความหมาย (Description) |
| :---: | :--- | :--- | :---: | :---: | :---: | :--- |
| 1 | `id` | `INT` | NO | PK | Auto Increment | รหัสประจำตัวหมวดเนื้อหา |
| 2 | `temple_id` | `INT` | NO | FK, Index | - | รหัสวัดที่สังกัด (`temples.id`) |
| 3 | `title` | `VARCHAR(255)` | NO | - | - | ชื่อหัวข้อของหมวดหมู่ |
| 4 | `content` | `TEXT` | NO | - | - | รายละเอียดเนื้อหา |
| 5 | `created_at` | `TIMESTAMP` | YES | - | `CURRENT_TIMESTAMP` | วันและเวลาที่สร้างข้อมูล |

---

### 4.3 ตาราง `users` (ผู้ใช้งานและผู้ดูแลระบบ)
> ตารางจัดการบัญชีผู้ใช้งาน สมาชิกทั่วไป และระดับสิทธิ์ของผู้ดูแลระบบ

| ลำดับ | ชื่อฟิลด์ (Field) | ชนิดข้อมูล (Data Type) | Nullable | คีย์ (Key) | ค่าเริ่มต้น (Default) | คำอธิบายความหมาย (Description) |
| :---: | :--- | :--- | :---: | :---: | :---: | :--- |
| 1 | `id` | `INT` | NO | PK | Auto Increment | รหัสผู้ใช้งาน |
| 2 | `username` | `VARCHAR(50)` | NO | Unique | - | บัญชีผู้ใช้งานสำหรับเข้าสู่ระบบ (ไม่ซ้ำกัน) |
| 3 | `password` | `VARCHAR(255)` | NO | - | - | รหัสผ่านที่ผ่านการเข้ารหัส Bcrypt Hash |
| 4 | `full_name` | `VARCHAR(100)` | NO | - | - | ชื่อ-นามสกุลจริงของผู้ใช้งาน |
| 5 | `role` | `VARCHAR(20)` | NO | - | `'user'` | ระดับสิทธิ์: `'user'`, `'admin'`, `'superadmin'` |
| 6 | `temple_id` | `INT` | YES | FK, Index | NULL | รหัสวัดที่สังกัด (`temples.id`) เฉพาะแอดมินวัด |
| 7 | `profile_image` | `VARCHAR(255)` | YES | - | NULL | ที่อยู่ไฟล์รูปโปรไฟล์ผู้ใช้งาน |
| 8 | `created_at` | `TIMESTAMP` | YES | - | `CURRENT_TIMESTAMP` | วันและเวลาที่สมัครสมาชิก |

---

### 4.4 ตาราง `bookable_items` (รายการบริการ/สิ่งของที่เปิดให้จอง)
> ทรัพยากรของวัดที่เปิดให้สาธุชนหรือญาติโยมทำการจองคิวหรือใช้งาน

| ลำดับ | ชื่อฟิลด์ (Field) | ชนิดข้อมูล (Data Type) | Nullable | คีย์ (Key) | ค่าเริ่มต้น (Default) | คำอธิบายความหมาย (Description) |
| :---: | :--- | :--- | :---: | :---: | :---: | :--- |
| 1 | `id` | `INT` | NO | PK | Auto Increment | รหัสประจำตัวสิ่งของ/บริการ |
| 2 | `temple_id` | `INT` | NO | FK, Index | - | รหัสวัดเจ้าของรายการ (`temples.id`) |
| 3 | `item_name` | `VARCHAR(150)` | NO | - | - | ชื่อของสิ่งของ เช่น ศาลา 1, อุปกรณ์งานศพ, พระสวด |
| 4 | `item_type` | `VARCHAR(50)` | NO | - | - | ประเภท: `pavilion_funeral`, `pavilion_merit`, `monk`, `equipment`, `fair_stall` |
| 5 | `description` | `TEXT` | YES | - | NULL | รายละเอียด อุปกรณ์ที่จัดเตรียมให้ หรือระเบียบการใช้ |
| 6 | `price` | `DECIMAL(10,2)` | NO | - | `0.00` | อัตราค่าบำรุงวัด (บาท) ถ้าไม่มีค่าใช้จ่ายใส่ 0 |
| 7 | `total_qty` | `INT` | NO | - | `1` | จำนวนที่มีให้จองพร้อมกันต่อวัน |
| 8 | `is_active` | `ENUM('yes','no')`| NO | - | `'yes'` | สถานะการเปิดรับจอง (`yes` = เปิด, `no` = ปิดชั่วคราว) |
| 9 | `created_at` | `TIMESTAMP` | YES | - | `CURRENT_TIMESTAMP` | วันและเวลาที่เพิ่มรายการ |

---

### 4.5 ตาราง `bookings` (ข้อมูลรายการจอง)
> ตารางบันทึกการจอง วันที่จอง ข้อมูลติดต่อ และขั้นตอนการอนุมัติ/ยกเลิก

| ลำดับ | ชื่อฟิลด์ (Field) | ชนิดข้อมูล (Data Type) | Nullable | คีย์ (Key) | ค่าเริ่มต้น (Default) | คำอธิบายความหมาย (Description) |
| :---: | :--- | :--- | :---: | :---: | :---: | :--- |
| 1 | `id` | `INT` | NO | PK | Auto Increment | รหัสรายการจอง (Booking ID) |
| 2 | `temple_id` | `INT` | NO | FK, Index | - | รหัสวัดที่ถูกจอง (`temples.id`) |
| 3 | `item_id` | `INT` | NO | FK, Index | - | รหัสสิ่งของ/บริการที่จอง (`bookable_items.id`) |
| 4 | `user_id` | `INT` | YES | FK, Index | NULL | รหัสผู้จองที่เป็นสมาชิก (`users.id`) |
| 5 | `booking_date` | `DATE` | NO | Index | - | วันที่ต้องการใช้งาน/จัดพิธี |
| 6 | `stall_no` | `VARCHAR(20)` | YES | - | NULL | เลขที่แผงค้า (กรณีจองแผงงานวัด `fair_stall`) |
| 7 | `qty_booked` | `INT` | NO | - | `1` | จำนวนที่ขอจอง |
| 8 | `status` | `ENUM(...)` | NO | - | `'pending'` | สถานะ: `'pending'`, `'approved'`, `'confirmed'`, `'success'`, `'rejected'`, `'cancelled'` |
| 9 | `contact_name` | `VARCHAR(100)` | NO | - | - | ชื่อผู้ประสานงาน/เจ้าภาพ |
| 10 | `contact_phone`| `VARCHAR(20)` | NO | - | - | หมายเลขโทรศัพท์ติดต่อผู้ประสานงาน |
| 11 | `admin_note` | `TEXT` | YES | - | NULL | บันทึกข้อความหรือเหตุผลจากแอดมินวัด |
| 12 | `cancelled_by` | `ENUM('user','admin')` | YES | - | NULL | ระบุว่าใครเป็นผู้ขอยกเลิกคำขอ |
| 13 | `cancelled_at` | `DATETIME` | YES | - | NULL | วันและเวลาที่กดยกเลิก |
| 14 | `created_at` | `TIMESTAMP` | YES | - | `CURRENT_TIMESTAMP` | วันและเวลาที่ส่งคำขอจอง |

---

### 4.6 ตาราง `donations` (ข้อมูลการบริจาคและทำบุญ)
> ตารางบันทึกยอดเงินทำบุญออนไลน์ หลักฐานสลิปโอนเงิน และสถานะการออกใบเสร็จ

| ลำดับ | ชื่อฟิลด์ (Field) | ชนิดข้อมูล (Data Type) | Nullable | คีย์ (Key) | ค่าเริ่มต้น (Default) | คำอธิบายความหมาย (Description) |
| :---: | :--- | :--- | :---: | :---: | :---: | :--- |
| 1 | `id` | `INT` | NO | PK | Auto Increment | รหัสรายการบริจาค |
| 2 | `temple_id` | `INT` | YES | FK, Index | NULL | รหัสวัดที่ได้รับบริจาค (`temples.id`) |
| 3 | `amount` | `DECIMAL(12,2)`| NO | - | - | จำนวนเงินทำบุญ (บาท) |
| 4 | `donor_name` | `VARCHAR(100)` | NO | - | `'ผู้ไม่ประสงค์ออกนาม'` | ชื่อผู้ร่วมทำบุญ |
| 5 | `anonymous` | `TINYINT(1)` | NO | - | `0` | สิทธิ์การแสดงชื่อ (`0` = ออกนามหน้าเว็บ, `1` = ปิดชื่อ) |
| 6 | `tax_deduction`| `ENUM('yes','no')`| NO | - | `'no'` | ความต้องการนำไปลดหย่อนภาษี |
| 7 | `status` | `ENUM(...)` | NO | - | `'pending'` | สถานะสลิป: `'pending'`, `'completed'`, `'failed'` |
| 8 | `slip_image` | `VARCHAR(255)` | YES | - | NULL | ที่อยู่ไฟล์สลิป (`donation_uploads/...`) |
| 9 | `receipt_no` | `VARCHAR(50)` | YES | - | NULL | เลขที่ใบเสร็จหรือหมายเลขอ้างอิงใบอนุโมทนา |
| 10 | `created_at` | `TIMESTAMP` | YES | Index | `CURRENT_TIMESTAMP` | วันและเวลาที่ทำรายการบริจาค |

---

### 4.7 ตาราง `events` (กิจกรรมและงานประจำปี)
> กิจกรรมงานบุญ เทศกาลวันสำคัญทางศาสนาที่จัดขึ้นโดยวัด

| ลำดับ | ชื่อฟิลด์ (Field) | ชนิดข้อมูล (Data Type) | Nullable | คีย์ (Key) | ค่าเริ่มต้น (Default) | คำอธิบายความหมาย (Description) |
| :---: | :--- | :--- | :---: | :---: | :---: | :--- |
| 1 | `id` | `INT` | NO | PK | Auto Increment | รหัสประจำตัวกิจกรรม |
| 2 | `temple_id` | `INT` | NO | FK, Index | - | รหัสวัดผู้จัดงาน (`temples.id`) |
| 3 | `event_name` | `VARCHAR(200)` | NO | - | - | ชื่องานบุญหรือกิจกรรม |
| 4 | `description` | `TEXT` | YES | - | NULL | กำหนดการและรายละเอียดงาน |
| 5 | `event_image` | `VARCHAR(255)` | YES | - | NULL | ไฟล์รูปภาพประชาสัมพันธ์งาน |
| 6 | `event_date` | `DATE` | NO | Index | - | วันที่จัดกิจกรรม |
| 7 | `event_time` | `TIME` | YES | - | NULL | เวลาเริ่มต้นกิจกรรม |
| 8 | `created_at` | `TIMESTAMP` | YES | - | `CURRENT_TIMESTAMP` | วันและเวลาที่เพิ่มกิจกรรม |

---

### 4.8 ตาราง `news` (ข่าวสารประชาสัมพันธ์)
> ข่าวสาร การพัฒนาวัด และประกาศทางการจากทางวัด

| ลำดับ | ชื่อฟิลด์ (Field) | ชนิดข้อมูล (Data Type) | Nullable | คีย์ (Key) | ค่าเริ่มต้น (Default) | คำอธิบายความหมาย (Description) |
| :---: | :--- | :--- | :---: | :---: | :---: | :--- |
| 1 | `id` | `INT` | NO | PK | Auto Increment | รหัสประจำตัวข่าวสาร |
| 2 | `temple_id` | `INT` | NO | FK, Index | - | รหัสวัดผู้เผยแพร่ข่าว (`temples.id`) |
| 3 | `title` | `VARCHAR(255)` | NO | - | - | หัวข้อข่าว |
| 4 | `content` | `TEXT` | NO | - | - | เนื้อหาข่าวโดยละเอียด |
| 5 | `image` | `VARCHAR(255)` | YES | - | NULL | รูปภาพประกอบข่าว |
| 6 | `created_at` | `TIMESTAMP` | YES | - | `CURRENT_TIMESTAMP` | วันและเวลาที่เผยแพร่ข่าว |

---

### 4.9 ตาราง `notifications` (ระบบแจ้งเตือน)
> การแจ้งเตือนความคืบหน้าของคำขอจอง การบริจาคเงิน และข่าวสารไปยังสมาชิกหรือแอดมิน

| ลำดับ | ชื่อฟิลด์ (Field) | ชนิดข้อมูล (Data Type) | Nullable | คีย์ (Key) | ค่าเริ่มต้น (Default) | คำอธิบายความหมาย (Description) |
| :---: | :--- | :--- | :---: | :---: | :---: | :--- |
| 1 | `id` | `INT` | NO | PK | Auto Increment | รหัสการแจ้งเตือน |
| 2 | `user_id` | `INT` | NO | FK, Index | - | รหัสผู้ใช้ที่จะได้รับการแจ้งเตือน (`users.id`) |
| 3 | `type` | `VARCHAR(50)` | NO | - | - | ประเภทแจ้งเตือน: `new_booking`, `booking_approved`, `booking_rejected`, `booking_cancelled`, `new_donation`, `new_member` |
| 4 | `ref_id` | `INT` | YES | - | NULL | รหัสอ้างอิงข้อมูล เช่น booking_id หรือ donation_id |
| 5 | `ref_table` | `VARCHAR(50)` | YES | - | NULL | ชื่อตารางอ้างอิง เช่น `'bookings'`, `'donations'` |
| 6 | `message` | `TEXT` | NO | - | - | ข้อความแจ้งเตือนที่แสดงบนหน้าจอ |
| 7 | `is_read` | `TINYINT(1)` | NO | Index | `0` | สถานะการอ่าน (`0` = ยังไม่อ่าน, `1` = อ่านแล้ว) |
| 8 | `created_at` | `TIMESTAMP` | YES | Index | `CURRENT_TIMESTAMP` | วันและเวลาที่สร้างการแจ้งเตือน |

---
*เอกสารนี้สร้างขึ้นโดยอ้างอิงโครงสร้างฐานข้อมูลและระบบการทำงานจริงของ ThumWadThaiHub ณ วันที่ 3 พฤศจิกายน 2026*
