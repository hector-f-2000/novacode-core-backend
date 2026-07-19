# INSTRUCCIONES DE DESARROLLO: CAPA DE DOMINIO E INFRAESTRUCTURA (MÓDULO TENANTS)

## 🎯 Objetivo

Implementar la estructura de Clean Architecture para el módulo de Clientes (`Tenants`) en el Core de NovaCode Labs. Se deben crear las carpetas, DTOs, Interfaces, Repositorios y el Caso de Uso para el registro de empresas, asegurando un tipado estricto compatible con PHP 7.4 y aislamiento total de la lógica de negocio.

---

## 📁 1. Estructura de Carpetas a Asegurar

El agente debe verificar o crear la siguiente estructura de directorios dentro de `app/`:

```text
app/
└── Core/
    └── Tenants/
        ├── Domain/
        │   ├── Contracts/      # Interfaces de Repositorios
        │   └── Entities/       # Entidades puras de dominio
        ├── Infrastructure/
        │   ├── Eloquent/       # Modelos nativos de Laravel y Repositorios concretos
        │   └── DTOs/           # Objetos de Transferencia de Datos
        └── Application/
            └── UseCases/       # Casos de Uso (Lógica de negocio pura)
```
