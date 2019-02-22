# Hiking DDS238-2 ZN/S energy meter

### Modbus holding registers:

| Register(s) | Meaning         | Scale Unit | Data format    | R/W |
|-------------|-----------------|------------|----------------|:---:|
| 0000h-0001h | total energy    | 1/100 kWh  | unsigned dword |  R¹ |
| 0002h-0003h | reserved        |            | unsigned dword |     |
| 0004h-0005h | reserved        |            | unsigned dword |     |
| 0006h-0007h | reserved        |            | unsigned dword |     |
| 0008h-0009h | export energy   | 1/100 kWh  | unsigned dword |  R  |
| 000Ah-000Bh | import energy   | 1/100 kWh  | unsigned dword |  R  |
| 000Ch       | voltage         | 1/10 V     | unsigned word  |  R  |
| 000Dh       | current         | 1/100 A    | unsigned word  |  R  |
| 000Eh       | active power    | 1 W        | signed   word  |  R  |
| 000Fh       | reactive power  | 1 VAr      | unsigned word  |  R  |
| 0010h       | power factor    | 1/1000     | unsigned word  |  R  |
| 0011h       | frequency       | 1/100 Hz   | unsigned word  |  R  |
| 0012h       | reserved        |            | unsigned word  |     |
| 0013h       | reserved        |            | unsigned word  |     |
| 0014h       | reserved        |            | unsigned word  |     |
| 0015h:high  | station address | 1-247      | unsigned char  | R/W |
| 0015h:low   | baud rate       | 1-4²       | unsigned char  | R/W |

#### Notes:

##### Note 1:

Total, export and import energy counters can erased writing 0 in total energy
registers.

##### Note 2:

Value mapping, default 1.

| Value | Baud rate |
|:-----:|:---------:|
| 1     | 9600 Bd   |
| 2     | 4800 Bd   |
| 3     | 2400 Bd   |
| 4     | 1200 Bd   |

##### Data formats

| Data format | Lenght  | Byte order |
|-------------|--------:|------------|
| char        |  8 bits |            |
| word        | 16 bits | Big endian |
| dword       | 32 bits | Big endian |


### Writing registers

The meter does not understand the 'write sigle register' function code (06h),
only the 'write multiple registers' function code (10h).
