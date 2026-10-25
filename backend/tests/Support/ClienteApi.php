<?php
// Cliente HTTP para las pruebas del API
// Cada instancia guarda su propia cookie de sesión, como si fuera una ventana distinta de la app

final class ClienteApi
{
    private string $cookies;

    // Cabeceras extra en cada petición (por ejemplo, Origin)
    public array $encabezados = [];

    public function __construct(private string $url)
    {
        $this->cookies = tempnam(sys_get_temp_dir(), 'sgt-cookies-');
    }

    public function __destruct()
    {
        @unlink($this->cookies);
    }

    // Regresa [codigo HTTP, cuerpo JSON decodificado]
    public function peticion(string $metodo, string $ruta, ?array $datos = null): array
    {
        $curl = curl_init($this->url . $ruta);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $metodo,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR => $this->cookies,
            CURLOPT_COOKIEFILE => $this->cookies,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', ...$this->encabezados],
        ]);
        if ($datos !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($datos));
        }

        $cuerpo = curl_exec($curl);
        $codigo = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        return [$codigo, json_decode((string) $cuerpo, true)];
    }

    // Cookies que guardó este cliente: nombre => valor (por ejemplo, la de sesión)
    public function cookies(): array
    {
        $cookies = [];
        foreach (file($this->cookies, FILE_IGNORE_NEW_LINES) as $linea) {
            $campos = explode("\t", $linea);
            if (count($campos) === 7) {
                $cookies[$campos[5]] = $campos[6];
            }
        }
        return $cookies;
    }

    public function nombresCookies(): array
    {
        return array_keys($this->cookies());
    }

    public function get(string $ruta): array
    {
        return $this->peticion('GET', $ruta);
    }

    public function post(string $ruta, array $datos = []): array
    {
        return $this->peticion('POST', $ruta, $datos);
    }

    public function patch(string $ruta, array $datos = []): array
    {
        return $this->peticion('PATCH', $ruta, $datos);
    }

    public function login(int $ID_Usuario, string $Password): array
    {
        return $this->post('/api/login', ['ID_Usuario' => $ID_Usuario, 'Password' => $Password]);
    }
}
