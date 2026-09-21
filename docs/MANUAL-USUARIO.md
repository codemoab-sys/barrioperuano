# Manual de usuario
## Sistema de Restaurante Barrio Peruano

**Version:** 1.0  
**Fecha:** 21 de septiembre de 2026  
**Aplicacion:** Sistema de ventas, mesas, cocina, delivery, inventario y administracion.

> Este manual esta dirigido al mozo, cajero, cajero/mozo, cocina y administrador. Cada persona debe usar su propio usuario. Nunca compartas contrasenas.

## 1. Acceso al sistema

### 1.1 Ingresar

1. Abre el enlace del sistema en el navegador.
2. En el campo **Correo electronico**, escribe tu correo registrado.
3. En el campo **Contrasena**, escribe tu contrasena.
4. Pulsa **Ingresar al Sistema**.
5. Si los datos son correctos, se mostrara el sistema segun tu rol.

### Captura 1. Pantalla de inicio de sesion

La pantalla muestra el logo de Barrio Peruano, el formulario de correo, contrasena y el boton naranja **Ingresar al Sistema**. A la izquierda se muestra la imagen del restaurante y el mensaje de gestion integral.

_[Insertar aqui la captura recibida: login del sistema]_ 

### 1.2 Cerrar sesion

1. Abre el menu de usuario.
2. Pulsa **Cerrar sesion**.
3. Cierra el navegador si estas usando un equipo compartido.

### 1.3 Problemas de acceso

- **Credenciales incorrectas:** revisa el correo y la contrasena.
- **Cuenta bloqueada:** solicita al administrador que revise la cuenta.
- **No tienes permiso:** tu rol no tiene acceso a ese modulo.
- **Debes abrir un turno de caja:** un cajero o administrador debe abrir la caja compartida.

## 2. Roles y permisos

| Rol | Puede hacer | No debe hacer |
|---|---|---|
| **Mozo** (`waiter`) | Ver sus areas asignadas, abrir mesas, registrar pedidos, editar items, enviar pedidos a cocina, consultar clientes y mostrar la precuenta | Confirmar pagos, emitir comprobantes, administrar productos, usuarios, configuracion o reportes financieros |
| **Cajero** (`cashier`) | Abrir y cerrar la caja compartida, POS, cobros, ventas, gastos, delivery, clientes y comprobantes autorizados | Administrar usuarios, inventario, configuracion general o mantenimiento |
| **Cajero/Mozo** (`cashier_waiter`) | Funciones de mozo y cajero: mesas, pedidos, cobros, ventas, gastos, delivery y clientes | Funciones exclusivas del administrador |
| **Cocina** (`kitchen`) | Ver el monitor de cocina y avanzar platos de pendiente a cocinando y servido | Cobrar, modificar precios, abrir caja o administrar usuarios |
| **Administrador** (`admin`) | Todos los modulos, configuracion, usuarios, areas, mesas, productos, inventario, reportes, caja, comprobantes, respaldos y mantenimiento | No debe borrar o restaurar datos sin confirmar un respaldo y la base correcta |

## 3. Regla de caja compartida

El sistema usa un solo turno abierto para todo el restaurante.

1. El cajero o administrador abre la caja al iniciar la jornada.
2. Todos los usuarios operativos usan ese mismo turno.
3. No se debe intentar abrir otra caja mientras exista una abierta.
4. Las ventas, gastos y deliveries quedan asociados al mismo turno.
5. Al terminar la jornada, el responsable revisa ventas y gastos y cierra la caja.
6. Despues del cierre, se debe abrir un nuevo turno para continuar vendiendo.

> Si no hay caja abierta, el mozo puede ver las mesas asignadas, pero no puede agregar productos ni cobrar.

## 4. Flujo diario recomendado

1. El administrador verifica usuarios, productos, precios y stock.
2. El cajero abre la caja con el monto inicial.
3. El mozo o cajero/mozo entra al POS.
4. Se selecciona el area y la mesa.
5. Se registra el pedido.
6. Cocina prepara el pedido y actualiza sus estados.
7. Se cobra la cuenta.
8. Se entrega el comprobante o ticket.
9. Se registran gastos si corresponde.
10. Al finalizar el turno, el cajero revisa ventas, gastos y cierra la caja.

## 5. Manual para el mozo

### 5.1 Ver mesas

1. Ingresa al sistema.
2. Abre **POS**.
3. Selecciona un area que tengas asignada.
4. Revisa el estado de las mesas.
5. Pulsa una mesa disponible para comenzar un pedido.

Si intentas abrir una mesa de un area no asignada, el sistema mostrara acceso denegado.

### Captura 2. Pantalla principal del POS

En esta pantalla se observa:

- El menu lateral con **Punto de Venta**, **Reservas**, **Cocina (KDS)** y **Clientes**.
- Las pestañas de las areas, por ejemplo **Barrio Peruano 1** y **Barrio Peruano 2**.
- Las mesas disponibles con el indicador verde **Libre**.
- La leyenda de estados: disponible, ocupada y reservada.
- El usuario conectado en la esquina superior derecha.

Para comenzar una venta, selecciona el area correcta y pulsa una mesa con estado **Libre**.

_[Insertar aqui la captura recibida: pantalla principal del POS]_ 

### 5.2 Registrar un pedido

1. Selecciona la mesa.
2. Busca una categoria de productos.
3. Pulsa cada producto para agregarlo.
4. Usa el buscador o lector de codigo si esta disponible.
5. Revisa cantidades y precios en el carrito.
6. Agrega observaciones, por ejemplo: sin cebolla o termino medio.
7. Confirma el pedido.
8. Verifica que el pedido aparezca en cocina.

### 5.3 Modificar un pedido pendiente

1. Abre nuevamente la mesa.
2. Aumenta o reduce la cantidad.
3. Actualiza la nota del producto si es necesario.
4. Elimina un producto solo antes de cerrar la venta.
5. Revisa el total actualizado.

El sistema valida el stock disponible y el stock reservado por otras mesas.

### 5.4 Cambiar una mesa

1. Abre la orden pendiente.
2. Selecciona **Mover mesa**.
3. Elige una mesa libre de un area permitida.
4. Confirma el cambio.

### 5.5 Mostrar la precuenta

El mozo no confirma el pago. Su responsabilidad termina en mostrar la precuenta y entregar el total al cajero.

1. Abre la orden pendiente.
2. Pulsa **Precuenta** o la opcion de vista previa de la orden.
3. Revisa productos, cantidades, descuentos y total.
4. Muestra la precuenta al cliente.
5. Solicita al cajero que realice el cobro y emita el comprobante.

### 5.6 Cobro de una mesa

El cobro solo puede realizarlo el **cajero**, **cajero/mozo** o **administrador**.

1. El cajero abre la orden pendiente.
2. Pulsa **Cobrar**.
3. Selecciona el medio de pago: efectivo, tarjeta o transferencia.
4. Para efectivo, escribe el monto recibido.
5. Verifica el vuelto.
6. Selecciona el tipo de comprobante: ticket, boleta o factura.
7. Selecciona o registra los datos del cliente cuando corresponda.
8. Confirma el cobro.
9. Entrega el ticket o comprobante.

Para una factura se necesita un cliente con RUC y razon social validos, ademas de una serie activa configurada.

## 6. Manual para el cajero

### 6.1 Abrir la caja

1. Ingresa con tu usuario de cajero.
2. Abre **Abrir caja** o la opcion de caja.
3. Escribe el monto inicial.
4. Verifica el monto fisico.
5. Pulsa **Abrir turno**.
6. Confirma que el sistema redirija al POS.

La caja queda disponible para mozos, cajeros y cajeros/mozos autorizados.

### 6.2 Registrar un gasto

1. Abre el modulo **Gastos**.
2. Escribe la descripcion.
3. Escribe el monto.
4. Confirma el registro.
5. Guarda el comprobante fisico del gasto.

Los gastos registrados afectan el monto esperado del cierre.

### 6.3 Revisar ventas

1. Abre **Ventas**.
2. Filtra por fecha o tipo de pago.
3. Revisa total, usuario, cliente y comprobante.
4. Abre una venta para ver su detalle o imprimir el ticket.

### 6.4 Cerrar la caja

1. Verifica que no haya cobros pendientes.
2. Revisa ventas en efectivo.
3. Revisa gastos registrados.
4. Cuenta el efectivo fisico.
5. Abre **Cerrar caja**.
6. Escribe el monto final contado.
7. Agrega observaciones si existe diferencia.
8. Confirma el cierre.
9. Compara monto esperado, monto contado y diferencia.

No cierres la caja si otra persona aun esta cobrando.

## 7. Manual para cajero/mozo

El cajero/mozo combina los dos flujos anteriores:

1. Abre o usa la caja compartida segun la organizacion del local.
2. Entra al POS.
3. Toma pedidos en sus areas permitidas.
4. Modifica pedidos pendientes.
5. Envia pedidos a cocina.
6. Cobra mesas y deliveries.
7. Registra gastos cuando corresponda.
8. Apoya al cajero en la revision del turno.

## 8. Manual para cocina

### 8.1 Monitor de cocina

1. Ingresa con el usuario de cocina.
2. Abre **Cocina**.
3. Revisa los pedidos ordenados del mas antiguo al mas reciente.
4. Lee mesa, productos, cantidades y notas.

### 8.2 Actualizar un plato

1. Cuando inicias la preparacion, pulsa el plato en estado **Pendiente**.
2. El estado cambia a **Cocinando**.
3. Cuando termina, pulsa nuevamente.
4. El estado cambia a **Servido**.
5. Entrega o avisa el pedido al salon.

No marques como servido un plato que aun no este listo.

## 9. Delivery

### 9.1 Crear un pedido

1. Abre **Delivery** y pulsa **Nuevo pedido**.
2. Escribe nombre, telefono, direccion y referencia.
3. Selecciona el medio de pago.
4. Selecciona los productos y cantidades.
5. Agrega tarifa, notas y horario si corresponde.
6. Selecciona repartidor si ya esta definido.
7. Confirma el pedido.

El sistema valida que exista caja abierta y stock suficiente.

### 9.2 Preparar y entregar

1. En el panel de delivery revisa pedidos pendientes.
2. Cambia a **Preparando** cuando cocina inicia.
3. Asigna o cambia el repartidor.
4. Cambia a **En camino** cuando sale del local.
5. Cobra el delivery desde su detalle.
6. Selecciona el comprobante.
7. Confirma el cobro para marcarlo como **Entregado**.

No marques un delivery como entregado desde el cambio manual de estado; debe cerrarse mediante el cobro.

### 9.3 Cancelar

1. Abre el delivery pendiente.
2. Pulsa **Cancelar**.
3. Confirma la cancelacion.
4. Verifica que el stock vuelva a estar disponible.

## 10. Reservas

1. Abre **Reservas**.
2. Pulsa **Nueva reserva**.
3. Selecciona mesa, fecha y hora.
4. Escribe nombre, telefono y cantidad de personas.
5. Confirma.
6. Actualiza el estado cuando el cliente llegue o cancele.

El sistema rechaza reservas pasadas, superpuestas o que excedan la capacidad de la mesa.

## 11. Clientes

1. Abre **Clientes**.
2. Pulsa **Nuevo cliente**.
3. Registra nombre, documento, telefono y datos solicitados.
4. Guarda.
5. Durante el cobro, busca el cliente para asociarlo a la venta.

Para factura, verifica el RUC y la razon social antes de cobrar.

## 12. Modulos del administrador

### 12.1 Dashboard y reportes

- **Dashboard:** resumen de actividad del restaurante.
- **Reportes:** ventas por categoria, rendimiento por usuario, productos mas vendidos y menos vendidos.
- Usa filtros de fecha antes de analizar los resultados.

### 12.2 Categorias

1. Abre **Categorias**.
2. Crea, edita, activa o desactiva categorias.
3. Usa nombres claros para facilitar el trabajo del POS.

### 12.3 Productos e inventario

1. Abre **Productos**.
2. Crea o edita nombre, categoria, precio, stock, imagen y codigo de barras.
3. Marca si el producto esta activo y vendible.
4. Configura ingredientes si utiliza receta.
5. Usa **Ajustar stock** para entradas o correcciones justificadas.
6. Revisa **Kardex** para ver movimientos y usuario responsable.

No reduzcas stock manualmente para corregir una venta; primero revisa la orden y el kardex.

### 12.4 Areas y mesas

1. Abre **Mapa de mesas**.
2. Crea o edita areas.
3. Activa o desactiva areas.
4. Crea mesas y define su capacidad.
5. Arrastra las mesas si el modulo permite cambiar su posicion.
6. Asigna areas a usuarios desde la gestion de usuarios.

### 12.5 Usuarios

1. Abre **Usuarios**.
2. Pulsa **Nuevo usuario**.
3. Escribe nombre, correo y contrasena temporal.
4. Selecciona el rol correcto.
5. Define si tiene acceso a todas las areas o solo a areas asignadas.
6. Guarda.
7. Entrega las credenciales por un canal privado y solicita cambio de contrasena.

### 12.6 Configuracion

1. Abre **Configuracion**.
2. Revisa nombre comercial, datos fiscales, moneda, impuestos, logo y colores.
3. Configura los datos de facturacion electronica solo con informacion oficial.
4. Guarda y verifica una venta de prueba en el entorno correspondiente.

No publiques tokens, claves SOL, certificados ni contrasenas en capturas o chats.

### 12.7 Comprobantes y notas

- **Comprobantes:** revisar boletas y facturas, consultar estado SUNAT, descargar PDF/XML/CDR y reenviar cuando sea necesario.
- **Nota de credito:** usar para anular o devolver una venta segun el procedimiento contable.
- **Nota de debito:** usar para cargos adicionales justificados.
- Antes de emitir o anular, verifica cliente, serie, correlativo y total.

### 12.8 Mantenimiento y respaldos

1. Abre **Sistema**.
2. Genera un respaldo antes de una operacion importante.
3. Guarda el archivo en un lugar seguro.
4. Restaura solo un respaldo verificado y con autorizacion.
5. Nunca uses reinicio de datos en produccion sin confirmar que la base es de pruebas.

## 13. Mensajes frecuentes

| Mensaje o situacion | Solucion |
|---|---|
| No hay caja abierta | Solicita al cajero o administrador que abra el turno compartido |
| Ya hay una caja abierta | Usa el turno existente; no abras otra caja |
| Producto sin stock | Revisa inventario, reservas de otras mesas o receta |
| Mesa ocupada | Abre la orden pendiente de esa mesa |
| Area no asignada | Solicita al administrador que asigne el area |
| No hay serie activa | El administrador debe configurar la serie por area o general |
| Cliente invalido para factura | Completa RUC y razon social validos |
| Delivery no puede entregarse | Realiza el cobro desde el detalle del delivery |
| Venta cerrada | No se puede modificar; revisa comprobantes o notas |

## 14. Guia para completar capturas

Guardar las imagenes en `docs/capturas/` usando estos nombres. Ocultar correos reales, telefonos, RUC, tokens, contrasenas y datos de clientes.

| Archivo | Pantalla | Usuario recomendado |
|---|---|---|
| `01-login.png` | Inicio de sesion | Cualquier usuario |
| `02-pos-mesas.png` | Mapa de areas y mesas | Mozo |
| `03-dashboard.png` | Panel principal | Administrador |
| `04-abrir-caja.png` | Formulario de apertura | Cajero |
| `05-pos-pedido.png` | Productos y carrito | Mozo |
| `06-cobro.png` | Ventana de cobro | Cajero |
| `07-cocina.png` | Monitor de cocina | Cocina |
| `08-delivery.png` | Panel de pedidos | Cajero/mozo |
| `09-reservas.png` | Agenda de reservas | Mozo |
| `10-clientes.png` | Lista o formulario de clientes | Cajero |
| `11-ventas-reportes.png` | Ventas y reportes | Administrador |
| `12-productos-stock.png` | Productos e inventario | Administrador |
| `13-usuarios.png` | Gestion de usuarios | Administrador |
| `14-configuracion.png` | Configuracion | Administrador |
| `15-comprobantes.png` | Facturas, boletas y notas | Administrador |
| `16-cerrar-caja.png` | Cierre y diferencia | Cajero |

### Como tomar una captura util

1. Abre solo la pantalla que se quiere explicar.
2. Maximiza el navegador.
3. Verifica que se vean menus, botones y datos de ejemplo.
4. Oculta informacion personal y credenciales.
5. Guarda la imagen con el nombre de la tabla.
6. Inserta la imagen debajo del paso correspondiente con Markdown.

Ejemplo:

```markdown
![Pantalla POS](capturas/05-pos-pedido.png)
```

## 15. Reglas de seguridad

- Cada trabajador usa su propio usuario.
- No compartas contrasenas.
- Cierra sesion al terminar.
- No fotografies contrasenas, tokens, certificados o datos fiscales sensibles.
- Verifica el monto antes de confirmar un cobro.
- Verifica el cliente antes de emitir una factura.
- No cierres la caja mientras existan operaciones pendientes.
- Genera respaldo antes de mantenimiento.
- Reporta errores con fecha, usuario, modulo y captura, sin datos sensibles.
