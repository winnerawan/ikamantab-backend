# Api-document

----------------* Request Tanpa Api Key *--------------------

### User

# Login

** `POST` `/api/v1/login`**

**_Parameters_**

* `email` - email saat mendaftar
* `password` - password saat mendaftar

**_Contoh_**

```js
      /api/v1/login
```

**_Response_**

```js
    {
        "error": false
        "name": "Winnerawan T"
        "email": "admin@winnerawan.net"
        "apiKey": "17a3d9bab2b1ddc1cef9a77e7b8de3a3"
        "createdAt": "2016-10-23 16:12:24"
     }
```

# Register

### 2 langkah untuk melakukan registrasi

#### 1. ** `POST` `/api/v1/register`**

    **_Parameters_**

      * `nama` - nama pengguna
      * `email` - email pengguna
      * `password` - password
      
**_Contoh_**

```js
      /api/v1/register
```

**_Response_**

```js
    {
        "error": false
        "message": "user registered"
     }
```

#### 2. ** `POST` `/api/v1/registerInfo`**

    **_Parameters_**

      * `jenis_kelamin` - jenis kelamin pengguna
      * `angkatan_lulus` - angkatan lulus pengguna
      * `jurusan_id` - jurusan_id pengguna 
      * `asrama_id` - asrama_id pengguna 

**_Contoh_**

```js
      /api/v1/registerInfo
```

**_Response_**

```js
    {
        "error": false
        "message": "user info registered"
    }
```

# List Jurusan

** `GET` `/api/v1/listJurusan`**

***_Parameters_*** 

`null` - tanpa parameter 

**_Contoh_**

      ```js      
          /api/v1/listJurusan
          ```
**_Response_**

```js
      {
            "error": false
            "jurusan": [
                  {
                  "jurusan_id": 1
                  "deskripsi": "IPA 1"
                  },
                  {
                  "jurusan_id": 2
                  "deskripsi: "IPA 2"
                  }
            ]
       }     
```

# List Asrama

** `GET` `/api/v1/listAsrama`**

***_Parameters_*** 

`null` - tanpa parameter 

**_Contoh_**

      ```js      
          /api/v1/listAsrama
          ```
**_Response_**

```js    
      {
            "error": false
            "asrama": [
                  {
                  "asrama_id": 1
                  "deskripsi": "Asrama 1"
                  },
                  {
                  "asrama_id": 2
                  "deskripsi: "Asrama 2"
                  }
             ]
       }      
```


