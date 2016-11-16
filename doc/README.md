# Api-document

##### ----------------* Request Tanpa Api Key *--------------------

## Login

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

## Register

### 2 langkah untuk melakukan registrasi

** `POST` `/api/v1/register`**

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

** `POST` `/api/v1/registerInfo`**

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

## List Jurusan

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
                  }
            ]
       }
```

## List Asrama

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
              }
         ]
    }
```

##### ----------------* Request Dengan Api Key *--------------------

```
Api Key digunakan untuk membatasi akses, atau untuk mangamankan informasi pengguna dari pengguna yang tidak terdaftar. Api Key disini masih sangat simple, digenerate secara otomomatis ketika pengguna mendaftar. Untuk mengakses informasi user misal nya, Api Key harus disertakan dalam 'HEADER REQUEST' dengan key 'Authorization' 
```

### Contoh dengan Retrofit 1.9 dan OkHttp 2.3.0

* Buat Class khusus agar tidak lagi menulis ulang banyak code (misal) RequestWithKey

**Class RequestWithKey**

```java
import retrofit.RequestInterceptor;
import retrofit.RestAdapter;

public class RequestWithKey {
    public RestAdapter RequestWithToken(final String api_key) {
        RestAdapter restAdapter = new RestAdapter.Builder()
                .setRequestInterceptor(new RequestInterceptor() {
                    @Override
                    public void intercept(RequestFacade request) {
                        request.addHeader("Authorization", api_key);
                        Log.e(TAG, request.toString());
                    }
                })
                .setEndpoint(AppConfig.BASE_URL)
                .build();

        return restAdapter;
    }
```

* Contoh Method Impl

```java
private void requestListUser(final String api_key) {
    RequestWithKey req = new RequestWithKey();
    ApiInterface api = req.RequestWithToken(api_key).create(ApiInterface.class);
    api.getlisuser...
    ...
    //TO DO
    //Your Business here
```

## Informasi User

** `GET` `/api/v1/myInformation`**

**_Parameters_**

* `null` - tanpa parameter

**_Contoh_**


```js
      /api/v1/myInformation
```

**_Response_**

```js
{
  "error": false,
  "users": [
    {
      "id": 2,
      "name": "Bowo",
      "email": "bowo@kuda.net",
s      "gcm": "ceGFjM14PAo:APA91bGzXbg7o8cG9xbuKKZxeUdLHtqQ5CWi9LocfmVWWjxB48q3UHyWzEVxNC5UReGB17qomy0h-sbx4XgZXHMRt9jwENC1NcXeO3Eeiy_kvO2HIJ3i8_AfO41ZLcDbFgk5_zLFjjLM",
      "foto": "http://localhost/api/v1/default-foto.png",
      "angkatan": 2001,
      "jurusan": "IPA 2",
      "bio": "Presient Kuda",
      "profesi": "Penunggang Kuda",
      "keahlian": "Gak punya",
      "penghargaan": "Gak punya juga",
      "minat_profesi": "Memerintah",
      "referensi_rekomendasi": null,
      "telp": null,
      "jenis_kelamin": "Laki-laki"
    }
  ]
}
```

* Response tanpa Api Key

**_Response_**

```js
    {
        "error": true
        "name": "Api key is misssing"
     }
```

* Response dengan Api Key yang salah

**_Response_**

```js
    {
        "error": true
        "name": "Access Denied. Invalid Api key"
     }
```

# List User

** `GET` `/api/v1/listUsers`**

**_Parameters_**

* `null` - tanpa parameter

**_Contoh_**


```js
      /api/v1/listUsers
```

**_Response_**

```js
{
  "error": false,
  "users": [
    {
      "id": 3,
      "name": "Dummy",
      "email": "xxx@xxx.net",
      "gcm": "fxaaVrpDyJw:APA91bGT60sssgIDRK60G09nOr1z_NeciV6Dj8TgPd-_cjnBLnVodbPj7W667_lD6NAXAoofPjhHMeeobOIInPnss4mRlWVT22gQD2iliITx0fB0RrpRsWrI6BrXE1UdzEXStEJ_yabH",
      "telp": null,
      "foto": "http://localhost/api/v1/default-foto.png",
      "angkatan": 2001,
      "jurusan": "IPS 2"
    }
  ]
}
```

* Response tanpa Api Key

**_Response_**

```js

    {
        "error": true
        "name": "Api key is misssing"
     }

```

* Response dengan Api Key yang salah

**_Response_**

```js
    {
        "error": true
        "name": "Access Denied. Invalid Api key"
     }
```


-------End Documentation---------

###### .....lain nya menyusul
