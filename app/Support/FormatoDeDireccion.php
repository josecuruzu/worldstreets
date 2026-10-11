<?php

namespace App\Support;

/**
 * Cómo se escribe una dirección en cada país.
 *
 * Es la pieza de contenido que el sitio no tenía y que su visitante necesita.
 * Search Console lo dice con bastante claridad: el público de EE.UU. busca
 * direcciones de OTROS países —sus dos consultas principales son
 * `reverse address lookup canada` y `canada reverse address lookup`— y hay 41
 * consultas suyas sobre códigos postales con 528 impresiones y **cero clics**.
 * O sea: preguntan, el sitio aparece, y la página no dice nada.
 *
 * Alguien en Ohio que le manda un paquete a Filipinas no sabe qué es un
 * barangay. A Canadá, no sabe que el código postal va `A1A 1A1` ni que la
 * provincia son dos letras. A Japón, no sabe que la dirección va de lo grande
 * a lo chico y que la mayoría de las calles no tienen nombre.
 *
 * Y tiene una propiedad que ninguna otra idea de contenido tiene: **sirve
 * igual con 380 calles que con 471.503**. La cobertura del dataset y la demanda
 * no coinciden —Canadá tiene 471.503 calles y Filipinas 1.764, con demanda
 * parecida—, así que la ficha de país necesitaba algo de valor que no dependa
 * de cuántas calles haya.
 *
 * Están solo los países donde el dato es firme. El resto no muestra el bloque:
 * media docena de líneas inventadas sobre el correo de un país es peor que no
 * decir nada, y este sitio ya viene de una penalización algorítmica.
 *
 * ── Procedencia del dato ──────────────────────────────────────────────────
 *
 * Los 21 países salieron del conocimiento del modelo. **Los 21 están
 * verificados contra fuente oficial** (18-sep-2026) y cada uno lleva arriba la
 * fuente y qué se corrigió, o que no hizo falta corregir nada.
 *
 * Nueve tenían mal la POSICIÓN de algún campo, casi siempre la del código
 * postal, que es justo el dato por el que llega esta consulta:
 *
 *   PH, MX, CU   el código abre la última línea, no la cierra
 *   CA           la unidad va antes del número de calle, con guion
 *   IN           el PIN va en su propia línea, debajo de la ciudad
 *   JP, CN       en alfabeto latino la dirección se escribe al REVÉS que en
 *                japonés o chino, y quien lee esta ficha escribe en latino
 *   ZA           la ciudad sobra cuando hay código postal
 *   ES           la provincia va siempre en su línea, repita o no la ciudad
 *
 * Y uno, PK, tenía dos campos apretados en una línea que el operador separa.
 * Los once restantes estaban bien y solo ganaron lo que el correo del país
 * pide y faltaba: CEDEX en Francia, el prefijo D- que no va en Alemania, las
 * mayúsculas italianas, el guion brasileño antes del estado, las regiones que
 * Nueva Zelanda no quiere.
 *
 * Las fuentes:
 *
 * · UPU, "Postal addressing systems in member countries" — la ficha que cada
 *   operador postal le declara a la Unión Postal Universal, con ejemplos
 *   comentados campo por campo:
 *   https://www.upu.int/UPU/media/upu/PostalEntitiesFiles/addressingUnit/{iso3}En.pdf
 *   (canEn, gbrEn, ausEn, indEn, phlEn, mexEn). Ojo: están fechadas —la de
 *   Filipinas es de 2004— así que las listas de provincias envejecen, pero la
 *   posición de los campos es la que el operador declara.
 * · Canada Post, "How to address mail and parcels".
 * · Australia Post, "Addressing guidelines".
 * · Royal Mail, guías de direccionamiento.
 * · Department of Posts (India), documento de política DHRUVA, mayo 2025.
 *
 * No es un detalle cosmético: `2000 PAMPANGA` y `PAMPANGA 2000` no son la
 * misma dirección para quien la copia en un sobre, y la tasa de error del
 * bloque —diez de veintiuno— dice que este contenido no se puede escribir de
 * memoria. Si mañana se agrega un país, se agrega con la ficha de la UPU
 * abierta al lado.
 */
final class FormatoDeDireccion
{
    /**
     * @return array{
     *     codigo: array{nombre: string, ejemplo: string, formato: string},
     *     lineas: list<string>,
     *     nota: string
     * }|null
     */
    public static function de(string $paisId): ?array
    {
        if (Idioma::esEspanol()) {
            return self::EN_ESPANOL[$paisId] ?? null;
        }

        return self::TODOS[$paisId] ?? null;
    }

    /** Los países con ficha, para saber la cobertura sin recorrer el array. */
    public static function paisesCubiertos(): array
    {
        return array_keys(Idioma::esEspanol() ? self::EN_ESPANOL : self::TODOS);
    }

    /*
     * ── La versión en español ─────────────────────────────────────────────
     *
     * Son cuatro y no veintiuno a propósito. Las páginas en español existen
     * solo para los 21 países hispanohablantes (ver `Idioma`), y de esos, los
     * que tienen ficha de dirección verificada son estos cuatro. Poner acá una
     * traducción de la ficha de Japón sería escribir contenido para una página
     * que no existe.
     *
     * El dato es el mismo que el inglés —las mismas fuentes oficiales, la
     * misma verificación del 18-sep-2026—, traducido. Si mañana se corrige uno
     * hay que corregir los dos: es la fragilidad que esto introduce y no se
     * puede evitar sin separar el dato del texto, que para cuatro fichas
     * cuesta más de lo que ahorra.
     *
     * Cuba primero porque es el segundo país del sitio por clics (832) y
     * porque su ficha —la del par de calles— es la que contesta `buscar
     * dirección en cuba`, que rankea en posición 7,7.
     */
    private const EN_ESPANOL = [

        'CU' => [
            'codigo' => [
                'nombre' => 'Código postal',
                'ejemplo' => '10400',
                'formato' => 'Cinco dígitos, con las letras CP delante.',
            ],
            'lineas' => [
                'Nombre del destinatario',
                'Calle y número, o «s/n» si no tiene número',
                'Las dos calles entre las que está — entre H e I',
                'Reparto, barrio o zona postal',
                'CP, los cinco dígitos y la localidad — CP 10600 CIUDAD HABANA',
            ],
            'nota' => 'El par de calles es lo que ubica de verdad una dirección cubana: «Calle 23 #456 entre H e I» quiere decir el número 456 de Calle 23, entre H e I. Sin eso, el número solo muchas veces no alcanza. El código postal abre la última línea y lleva las letras CP delante.',
        ],

        'ES' => [
            'codigo' => [
                'nombre' => 'Código postal',
                'ejemplo' => '28013',
                'formato' => 'Cinco dígitos. Los dos primeros son la provincia.',
            ],
            'lineas' => [
                'Nombre del destinatario',
                'Tipo de vía, nombre, número, piso y puerta',
                'Código postal y municipio',
                'Provincia, en su propia línea',
            ],
            'nota' => 'El número va después del nombre de la calle, y el piso y la puerta se añaden tras una coma: «Calle Mayor 14, 3.º B» es el número 14, tercer piso, puerta B. La provincia va en su propia línea debajo del municipio, y Correos la escribe aunque repita el nombre del municipio: 45012 TOLEDO, y debajo TOLEDO.',
        ],

        'MX' => [
            'codigo' => [
                'nombre' => 'Código Postal (CP)',
                'ejemplo' => '44100',
                'formato' => 'Cinco dígitos.',
            ],
            'lineas' => [
                'Nombre del destinatario',
                'Calle y número exterior, número interior',
                'Colonia',
                'CP, ciudad o municipio, abreviatura del estado',
            ],
            'nota' => 'El número va después del nombre de la calle. La colonia es obligatoria: el mismo nombre de calle se repite en varias colonias de la misma ciudad. El CP abre la última línea en vez de cerrarla, y el estado va abreviado — 44100 GUADALAJARA, JAL.',
        ],

        'PR' => [
            'codigo' => [
                'nombre' => 'ZIP code',
                'ejemplo' => '00901',
                'formato' => 'Cinco dígitos, y cuatro más opcionales detrás de un guion.',
            ],
            'lineas' => [
                'Nombre del destinatario',
                'Urbanización, condominio o barrio',
                'Calle y número, apartamento',
                'Ciudad, PR, ZIP code',
            ],
            'nota' => 'Puerto Rico usa el correo de Estados Unidos, así que desde allá se escribe como una dirección nacional con PR de estado. Lo que cambia es la línea de la urbanización o el barrio, que casi siempre hace falta, y que «Calle» va delante del nombre. El servicio postal abrevia URB, COND y APT.',
        ],

    ];

    private const TODOS = [
        // Verificado 18-sep-2026: Canada Post, "How to address mail and
        // parcels", y UPU/Canada 09/2014. Corregido el orden de la unidad.
        'CA' => [
            'codigo' => [
                'nombre' => 'Postal code',
                'ejemplo' => 'T2G 2N9',
                'formato' => 'Six characters — letter, digit, letter, space, digit, letter, digit.',
            ],
            'lineas' => [
                'Recipient name',
                'Unit number, hyphen, street number, street name',
                'City, two-letter province code, postal code',
                'CANADA',
            ],
            'nota' => 'The unit or apartment number goes first, joined to the street number by a hyphen: 10-123 MAIN ST NW, not 123 MAIN ST APT 10. The house number goes before the street name, and the province is a two-letter code — AB, ON, QC, BC. Canada Post asks for the last line in capitals, with two spaces before the postal code, and the space in the middle of the postal code is part of it.',
        ],

        // Verificado 18-sep-2026: UPU/United Kingdom y las guías de Royal
        // Mail. Las líneas ya coincidían.
        'GB' => [
            'codigo' => [
                'nombre' => 'Postcode',
                'ejemplo' => 'SW1A 1AA',
                'formato' => 'Two parts separated by a space: an outward code of two to four characters and an inward code of three.',
            ],
            'lineas' => [
                'Recipient name',
                'House number or house name, street',
                'Locality (if there is one)',
                'Post town',
                'Postcode',
            ],
            'nota' => 'The postcode narrows an address down to a handful of houses, so it does most of the work. The post town line is still required even when the postcode is right, and both are written in capitals, the postcode on a line of its own. The county is not needed at all once the post town and the postcode are there — Royal Mail advises leaving it out.',
        ],

        // Verificado 18-sep-2026: Australia Post, "Addressing guidelines", y
        // UPU/Australia 07/2017.
        'AU' => [
            'codigo' => [
                'nombre' => 'Postcode',
                'ejemplo' => '2000',
                'formato' => 'Four digits.',
            ],
            'lineas' => [
                'Recipient name',
                'Unit number / street number, street name',
                'Suburb, state abbreviation, postcode — in capitals',
                'AUSTRALIA',
            ],
            'nota' => 'The "suburb" line is the locality name and it is required — it is not a neighbourhood of a bigger city. States are abbreviated: NSW, VIC, QLD, WA, SA, TAS, ACT, NT. Unit and floor identifiers go before the street number, on the same line: LEVEL 6 51 JACOBSON ST. Australia Post asks for the last two lines in capitals.',
        ],

        // Verificado 18-sep-2026: UPU/Ireland (An Post). Las líneas ya
        // coincidían, incluida la del condado.
        'IE' => [
            'codigo' => [
                'nombre' => 'Eircode',
                'ejemplo' => 'D02 AF30',
                'formato' => 'Seven characters: a three-character routing key, a space, and a four-character unique identifier.',
            ],
            'lineas' => [
                'Recipient name',
                'House number or house name, street',
                'Town or locality',
                'County, unless it repeats the name of the town',
                'Eircode',
            ],
            'nota' => 'An Eircode identifies one individual address rather than a block, which is unusual — it is what makes the rest of the address optional rather than the other way round. Many rural addresses have no street name at all and are identified by the townland instead. The county is written with the "CO." prefix: CO. CORK.',
        ],

        // Verificado 18-sep-2026: UPU/India 01/2013 (el PIN va debajo de la
        // localidad, no en su línea) y el documento de política DHRUVA del
        // Department of Posts, mayo 2025, para el carácter descriptivo.
        'IN' => [
            'codigo' => [
                'nombre' => 'PIN code',
                'ejemplo' => '110001',
                'formato' => 'Six digits, written without a space.',
            ],
            'lineas' => [
                'Recipient name',
                'Flat or house number, building name',
                'Street or road',
                'Locality or area',
                'City',
                'State',
                'PIN code, on a line of its own',
            ],
            'nota' => 'The locality or area line usually matters more than the street name, and a landmark or a "near such-and-such" is often part of the address: the Department of Posts itself describes Indian addressing as largely descriptive, especially outside the big cities. The PIN code goes below the city or to the right of it, and it is what routes the delivery.',
        ],

        // Verificado 18-sep-2026: UPU/Philippines. El ZIP abre la última
        // línea, no la cierra.
        'PH' => [
            'codigo' => [
                'nombre' => 'ZIP code',
                'ejemplo' => '1226',
                'formato' => 'Four digits.',
            ],
            'lineas' => [
                'Recipient name',
                'Unit or house number, street name',
                'Barangay, city or municipality',
                'ZIP code, province',
            ],
            'nota' => 'The barangay is the smallest administrative unit and it is usually essential — in many places an address is found by barangay first and street second. The ZIP code opens the last line, before the province: 2000 PAMPANGA. Metro Manila addresses name the city (Makati, Quezon City) and close with METRO MANILA rather than "Manila".',
        ],

        // Verificado 18-sep-2026: UPU/Mexico 03/2017. El CP abre la última
        // línea y el estado va abreviado.
        'MX' => [
            'codigo' => [
                'nombre' => 'Código Postal (CP)',
                'ejemplo' => '44100',
                'formato' => 'Five digits.',
            ],
            'lineas' => [
                'Recipient name',
                'Street name and exterior number, interior number',
                'Colonia (the neighbourhood)',
                'CP, city or municipality, state abbreviation',
            ],
            'nota' => 'The number goes after the street name, not before it. The colonia is the neighbourhood and it is required: the same street name repeats across colonias in the same city. The CP opens the last line rather than closing it, and the state is abbreviated — 44100 GUADALAJARA, JAL.',
        ],
        // Verificado 18-sep-2026: UPU/Japan. Corregido el orden: en alfabeto
        // latino la dirección se escribe al revés que en japonés, y quien lee
        // esta ficha escribe en latino.
        'JP' => [
            'codigo' => [
                'nombre' => 'Postal code',
                'ejemplo' => '100-0001',
                'formato' => 'Seven digits with a hyphen after the third, often written after the postal mark.',
            ],
            'lineas' => [
                'Recipient name',
                'Zone, block and house number, then the district — 4-3-2, Hakusan',
                'Ward or town, prefecture — Bunkyo-ku, TOKYO',
                'Postal code',
                'JAPAN',
            ],
            'nota' => 'This is the one that surprises people most: most Japanese streets have no name. An address locates a numbered house inside a block inside a zone, not a position along a street. Written in Japanese it runs from largest to smallest and ends with the recipient; written in the Latin alphabet it is turned around, as above. Japan Post only sorts kanji and kana automatically, so a Latin-script address is sorted by hand — correct, but slower.',
        ],

        // Verificado 18-sep-2026: UPU/China. Las líneas estaban en el orden
        // chino, que contradice la propia nota, y no decían dónde va el
        // código postal.
        'CN' => [
            'codigo' => [
                'nombre' => 'Postal code',
                'ejemplo' => '100006',
                'formato' => 'Six digits.',
            ],
            'lineas' => [
                'Recipient name',
                'Room, floor, building',
                'Street and number',
                'District',
                'Postal code, then the city or province — 100004 BEIJING',
                'P.R. CHINA',
            ],
            'nota' => 'Written in Chinese the parts run from largest to smallest and end with the recipient; written in the Latin alphabet they are reversed, as above. The postal code opens the city line rather than closing it. The district matters as much as the street — large cities repeat street names across districts. The country name has to be written in a language the sending country understands, not in Chinese.',
        ],

        // Verificado 18-sep-2026: UPU/France (La Poste). Las líneas ya
        // coincidían; se sumó CEDEX.
        'FR' => [
            'codigo' => [
                'nombre' => 'Code postal',
                'ejemplo' => '75001',
                'formato' => 'Five digits. The first two identify the département.',
            ],
            'lineas' => [
                'Recipient name',
                'Number, street type and street name',
                'Code postal and commune',
                'FRANCE',
            ],
            'nota' => 'The street type is part of the name, not a suffix: rue, avenue, boulevard, impasse, allée, place. In Paris the last two digits of the code postal are the arrondissement — 75011 is the 11th. If the town is followed by the word CEDEX, never drop it: it is a separate business delivery service, and without it the item loses that handling. La Poste asks for the last three lines in capitals and no punctuation from the street line down.',
        ],

        // Verificado 18-sep-2026: UPU/Germany (Deutsche Post). Las líneas ya
        // coincidían; se sumó el prefijo D- y se matizó lo de "str.".
        'DE' => [
            'codigo' => [
                'nombre' => 'Postleitzahl (PLZ)',
                'ejemplo' => '10115',
                'formato' => 'Five digits.',
            ],
            'lineas' => [
                'Recipient name',
                'Street name and number',
                'PLZ and city',
                'GERMANY',
            ],
            'nota' => 'The number goes after the street name. Never write a country prefix in front of the postcode — not D-10115 and not DE-10115: Deutsche Post says it delays machine sorting. "Straße" is shortened to "str." and joined to the name when the name is a noun or a person (Kantstr. 12), but stays a separate word when it comes from a place name ending in -er (Rhondorfer Str. 666).',
        ],

        // Verificado 18-sep-2026: UPU/Spain. La provincia va siempre en su
        // propia línea: el ejemplo de Correos repite "45012 TOLEDO / TOLEDO".
        'ES' => [
            'codigo' => [
                'nombre' => 'Código postal',
                'ejemplo' => '28013',
                'formato' => 'Five digits. The first two identify the province.',
            ],
            'lineas' => [
                'Recipient name',
                'Street type, street name, number, floor and door',
                'Código postal and city',
                'Province, on a line of its own',
            ],
            'nota' => 'The number follows the street name, and the floor and door are usually appended after a comma — "Calle Mayor 14, 3º B" means number 14, third floor, door B. The province goes on its own line below the town, and Correos writes it even when it repeats the town name: 45012 TOLEDO, then TOLEDO.',
        ],

        // Verificado 18-sep-2026: UPU/Italy (Poste Italiane). Las líneas ya
        // coincidían.
        'IT' => [
            'codigo' => [
                'nombre' => 'CAP',
                'ejemplo' => '00184',
                'formato' => 'Five digits.',
            ],
            'lineas' => [
                'Recipient name',
                'Street type, street name, number',
                'CAP, comune, two-letter province',
            ],
            'nota' => 'The number follows the street name, never before it. The two-letter province code after the town — RM for Rome, MI for Milan — is part of the address. Poste Italiane requires the street line, the town line and the country line in capitals.',
        ],
        // Verificado 18-sep-2026: UPU/Brazil (Correios). Las líneas ya
        // coincidían.
        'BR' => [
            'codigo' => [
                'nombre' => 'CEP',
                'ejemplo' => '01310-100',
                'formato' => 'Eight digits with a hyphen before the last three.',
            ],
            'lineas' => [
                'Recipient name',
                'Street type, street name, number, complement',
                'Bairro',
                'City, two-letter state',
                'CEP',
            ],
            'nota' => 'The bairro is the neighbourhood and it is required. The number follows the street name after a comma, and the complement covers apartment or block — apto 51, bloco B. The two-letter state follows the town separated by a dash (GUARAPUAVA – PR) and never goes in front of the CEP, whose digits are never broken up with dots.',
        ],

        // Verificado 18-sep-2026: UPU/South Africa. Sobraba la línea de
        // ciudad: el correo sudafricano la pide solo si NO hay código.
        'ZA' => [
            'codigo' => [
                'nombre' => 'Postal code',
                'ejemplo' => '8001',
                'formato' => 'Four digits.',
            ],
            'lineas' => [
                'Recipient name',
                'Street number and street name',
                'Suburb or locality — in capitals',
                'Postal code',
            ],
            'nota' => 'The line above the postal code is the locality that the code identifies, and that is usually the suburb rather than the city. The South African Post Office asks for the city or the province only when there is no postal code, so a complete address for Fish Hoek ends "FISH HOEK 7975" with no Cape Town in it. A street address and a postal address are frequently different — a lot of post goes to a box.',
        ],

        // Verificado 18-sep-2026: UPU/New Zealand (NZ Post). Las líneas ya
        // coincidían; la nota pasó a la regla que el operador sí publica.
        'NZ' => [
            'codigo' => [
                'nombre' => 'Postcode',
                'ejemplo' => '6011',
                'formato' => 'Four digits.',
            ],
            'lineas' => [
                'Recipient name',
                'Unit, street number, street name',
                'Suburb',
                'City, postcode',
            ],
            'nota' => 'The suburb line goes above the town or city and is required within the main centres. Region and province names are not used at all — NZ Post asks for them to be left out, along with the names of nearby cities. The postcode closes the town line, one or two spaces after it: WELLINGTON 6011.',
        ],

        // Verificado 18-sep-2026: UPU/Pakistan. Casa y calle van en líneas
        // separadas, y el código se pega a la ciudad con guion.
        'PK' => [
            'codigo' => [
                'nombre' => 'Postal code',
                'ejemplo' => '44000',
                'formato' => 'Five digits.',
            ],
            'lineas' => [
                'Recipient name',
                'House number',
                'Street number or name',
                'Sector or block',
                'City and postal code, joined by a dash — ISLAMABAD-44000',
            ],
            'nota' => 'In the planned cities the sector and block do more work than any street name: an Islamabad address like House 17-B, Street 30, Sector F-7/1 is found by the sector code first, and the house and the street get a line each. The postal code hangs off the city name with a dash instead of standing on its own.',
        ],

        // Verificado 18-sep-2026: UPU/Sri Lanka. Las líneas ya coincidían. Lo
        // de los distritos numerados de Colombo NO está en la ficha de la UPU
        // —es uso corriente y el buscador de códigos de Sri Lanka Post los
        // lista—, así que la nota quedó redactada sin afirmar de más.
        'LK' => [
            'codigo' => [
                'nombre' => 'Postal code',
                'ejemplo' => '00100',
                'formato' => 'Five digits.',
            ],
            'lineas' => [
                'Recipient name',
                'House number, street name',
                'City',
                'Postal code',
            ],
            'nota' => 'The postal code goes on a line of its own, below the town. Colombo addresses usually carry a numbered district in place of a suburb — "Colombo 3" and "Colombo 7" are different parts of the city, each with its own code, so the number is worth keeping.',
        ],

        // Verificado 18-sep-2026: UPU/United States, que trae el ejemplo
        // puertorriqueño con URB. Sin cambios.
        'PR' => [
            'codigo' => [
                'nombre' => 'ZIP code',
                'ejemplo' => '00901',
                'formato' => 'Five digits, optionally followed by a hyphen and four more.',
            ],
            'lineas' => [
                'Recipient name',
                'Urbanización, condominio or barrio',
                'Calle and number, apartment',
                'City, PR, ZIP code',
            ],
            'nota' => 'Puerto Rico uses the US postal system, so a US sender writes it like a domestic address with PR as the state. What differs is the urbanización or barrio line, which is usually needed, and that "Calle" comes before the street name.',
        ],

        // Verificado 18-sep-2026: UPU/Cuba. El código va al principio de la
        // última línea y precedido por las letras CP.
        'CU' => [
            'codigo' => [
                'nombre' => 'Código postal',
                'ejemplo' => '10400',
                'formato' => 'Five digits.',
            ],
            'lineas' => [
                'Recipient name',
                'Calle and number, or "s/n" when there is no number',
                'The two cross streets — entre H e I',
                'Reparto, barrio or postal zone',
                'CP, the five digits, then the locality — CP 10600 CIUDAD HABANA',
            ],
            'nota' => 'The cross-street pair is how a Cuban address is actually located — "Calle 23 #456 entre H e I" means number 456 on Calle 23, between H and I. Without it the number alone is often not enough. The código postal opens the last line and carries the letters CP in front of it.',
        ],

        // Verificado 18-sep-2026: UPU/Jamaica, que confirma lo menos obvio de
        // toda la tabla: Jamaica no tiene código postal. Sin cambios.
        'JM' => [
            'codigo' => [
                'nombre' => 'Parish',
                'ejemplo' => 'Kingston 5',
                'formato' => 'Jamaica has no postal code in general use — the parish, and in Kingston a numbered postal district, take its place.',
            ],
            'lineas' => [
                'Recipient name',
                'House number, street name',
                'District or town',
                'Parish',
                'JAMAICA',
            ],
            'nota' => 'The parish does the work a postcode would: St. Andrew, St. Catherine, Manchester. Kingston addresses add a number — "Kingston 5" — that behaves like a district code.',
        ],
    ];
}
