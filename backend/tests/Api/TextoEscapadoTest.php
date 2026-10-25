<?php
// Migración 007: el texto que DOMPurify guardó como HTML ("Pan &amp; Cía") regresa a su texto original
// Se simulan datos guardados con el error y se vuelve a aplicar la migración, como en una instalación existente

final class TextoEscapadoTest extends ApiTestCase
{
    private const MIGRACION = '007_texto_sin_escapar_formularios';

    private function aplicarOtraVez(): string
    {
        BaseDatosPrueba::conexion()->exec("DELETE FROM Migracion WHERE Version = '" . self::MIGRACION . "'");
        return BaseDatosPrueba::ejecutarSeeder();
    }

    public function testRegresaElTextoDeLosFormulariosASuFormaOriginal(): void
    {
        $bd = BaseDatosPrueba::conexion();
        $bd->exec("UPDATE Producto SET Nombre_Producto = 'Pan &amp; Cía ' WHERE ID_Producto = 1");
        $bd->exec("UPDATE Categoria SET Nombre_Categoria = 'Frutas &amp; Verduras &lt;3' WHERE ID_Categoria = 1");
        $bd->exec("UPDATE Proveedor SET Nombre_Proveedor = 'Hnos. &lt;Pérez&gt;', Telefono = '656&amp;' WHERE ID_Proveedor = 1");
        $bd->exec("UPDATE Empleado SET Nombre = 'Rosa &amp; Hijos' WHERE ID_Empleado = 1");
        $bd->exec("UPDATE Contacto_Empleado SET Calle = 'Av. 5 &lt;esq&gt;', Colonia = 'Centro&nbsp;Sur', Correo = 'a&amp;b@x.mx'
            WHERE ID_Contacto_Empleado = (SELECT MIN(ID_Contacto_Empleado) FROM (SELECT ID_Contacto_Empleado FROM Contacto_Empleado) c)");

        $salida = $this->aplicarOtraVez();

        $this->assertStringContainsString(self::MIGRACION, $salida);
        $this->assertSame('Pan & Cía ', $this->valor('SELECT Nombre_Producto FROM Producto WHERE ID_Producto = 1'));
        $this->assertSame('Frutas & Verduras <3', $this->valor('SELECT Nombre_Categoria FROM Categoria WHERE ID_Categoria = 1'));
        $this->assertSame(['Hnos. <Pérez>', '656&'], array_values($this->fila('SELECT Nombre_Proveedor, Telefono FROM Proveedor WHERE ID_Proveedor = 1')));
        $this->assertSame('Rosa & Hijos', $this->valor('SELECT Nombre FROM Empleado WHERE ID_Empleado = 1'));
        $contacto = $this->fila('SELECT Calle, Colonia, Correo FROM Contacto_Empleado ORDER BY ID_Contacto_Empleado LIMIT 1');
        $this->assertSame(['Av. 5 <esq>', "Centro\u{00A0}Sur", 'a&b@x.mx'], array_values($contacto));
    }

    public function testNoGeneraEntidadesNuevasNiTocaElTextoCorrecto(): void
    {
        // Lo que el usuario escribió como "&lt;" se guardó como "&amp;lt;": regresa a "&lt;", no a "<"
        $bd = BaseDatosPrueba::conexion();
        $bd->exec("UPDATE Producto SET Nombre_Producto = 'Signo &amp;lt; menor' WHERE ID_Producto = 1");
        $bd->exec("UPDATE Producto SET Nombre_Producto = 'Precio > 10 & menos' WHERE ID_Producto = 2");

        $this->aplicarOtraVez();

        $this->assertSame('Signo &lt; menor', $this->valor('SELECT Nombre_Producto FROM Producto WHERE ID_Producto = 1'));
        $this->assertSame('Precio > 10 & menos', $this->valor('SELECT Nombre_Producto FROM Producto WHERE ID_Producto = 2'));
    }
}
