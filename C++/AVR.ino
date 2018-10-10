#include <Wire.h>   
#include "math.h"
#include "LedControl.h"

static int pwmPIN = 5;

static int pinA = 2;
static int pinB = 3;
static int pinC = 19;
static int pinD = 18;
volatile byte aFlag = 0;
volatile byte bFlag = 0;
volatile byte cFlag = 0;
volatile byte dFlag = 0;
volatile byte reading = 0;
volatile byte reading2 = 0;

LedControl lc = LedControl(12, 11, 10, 4);

const int WAIT = 0;
const int SELECT = 1;
const int BEGIN = 2;
const int CONTRACTION = 3;
const int INFLECTION = 4;
const int RETRACTION = 5;
const int CONSTANT = 6;
const int below = 0;
const int between = 1;
const int above = 2;
const double AUTO = 4;

void setup() {
  Wire.begin();
  Serial.begin(9600);

  pinMode(pinA, INPUT_PULLUP);
  pinMode(pinB, INPUT_PULLUP);
  pinMode(pinC, INPUT_PULLUP);
  pinMode(pinD, INPUT_PULLUP);
  attachInterrupt(digitalPinToInterrupt(pinA),PinAtrigger,RISING);
  attachInterrupt(digitalPinToInterrupt(pinB),PinBtrigger,RISING);
  attachInterrupt(digitalPinToInterrupt(pinC),PinCtrigger,RISING);
  attachInterrupt(digitalPinToInterrupt(pinD),PinDtrigger,RISING);

  analogWrite(pwmPIN, 0);

  init_matrix();
}

const double optFactor = 1000000;    
unsigned long timeLast = 0;
int position = 0;
int dir_disp = 0;   
int positionLast = 0;
double velocity = 0;
double velocity_avg = 0;
int disp_tol = 0; 

const double maxForce = 30;   
double baselineForce = maxForce;    
double force = baselineForce;   
double lc_force = 0;    
double voltage = 0;
int pwm_val = 0;

int pos_array[20] = {0};
unsigned long time_array[20] = {0};
double vel_2s = 0;
unsigned long timer_10th_sec = 0;
unsigned long timer_2_sec = 0;
double pos_avg = 0;
double pos_counter = 0;
unsigned long pos_timer = 0;

int state = WAIT;
int stage = below;
int previous_stage = below;
unsigned long stage_time = 0;
unsigned long previous_stage_time = 0;
const int lower_threshold = 30;
const int upper_threshold = 90;
int previous_state = WAIT;
int prev_prev_state = WAIT;

bool vars_initialized = false;
unsigned long Reset_timer = 0;
double weight_select = 0;
unsigned long weight_select_timer = 0;
double weight_select_count = 0;

double force_before_spot = force;
bool spot_flag = false;

double new_force = force;
double rad = 0;

unsigned long BEGIN_timer = 0;
unsigned long WAIT_timer = millis();
double lc_startingPoint = maxForce;

unsigned long time_last = millis();
int loopCounter = 0;
double previous_force = force;
double matrix_val = 1111;

double knob_select = AUTO;
unsigned long knob_timer = millis();
bool contraction_start = false;

void loop() {

  lc_force = LoadCell_val();

  switch (state) {

    case WAIT:

      Reset(); position=0;
      analogWrite(pwmPIN, 0);
      scale_tare(); 
      lc_force = LoadCell_val();

      if (millis()-WAIT_timer > 2000) {
        if ((lc_force < -0.5+1.86 || lc_force > 1+1.86)) {
          if (loopCounter%3 == 0) scale_tare();
        } else { 
          analogWrite(pwmPIN, force_to_pwm(force));
          delay(1500);
          writeToMatrix(2222);
          next_state(SELECT);
        }
      }

      break;

    case SELECT:

      if (knob_select > AUTO) {
        if (millis()-knob_timer > 3000) {
          force = knob_select;
          force_to_pwm(force);
          if (millis()-knob_timer > 4000) {
            writeToMatrix(3333);
            next_state(CONSTANT);
          }
        }
      } else {
        if (millis()-knob_timer > 3000 && matrix_val != 2222) writeToMatrix(2222);

        lc_force = LoadCell_val();
        if (lc_force > 1+1.86 && lc_force < maxForce - 5+1.86) {
          weight_select_timer = millis();
          next_state(BEGIN);
        }
      }

      if (position > 10 && lc_force < 0.5*force) {
          writeToMatrix(force);
          next_state(CONTRACTION);
      }

      break;

    case BEGIN:

      if (knob_select != AUTO) {
        next_state(SELECT);
        break;
      }

      lc_force = LoadCell_val();  

      if (lc_force > 1+1.86 && lc_force < maxForce - 5+1.86) {    
        if (millis() - weight_select_timer < 5000) {
          weight_select_count++;
          weight_select += lc_force;
          if (millis() - weight_select_timer > 1000 && matrix_val != 3333)
            writeToMatrix(maxForce - lc_force);
        } else {
          weight_select_timer = millis();
          baselineForce = 1.1*(maxForce - weight_select/weight_select_count);
          force = baselineForce;

          writeToMatrix(3333);
        }

      } else {
        weight_select_timer = millis();
        weight_select = 0;
        weight_select_count = 0;
        force = baselineForce;

        if (matrix_val != 3333) {
          writeToMatrix(2222);
          next_state(SELECT);
        }
      }

      if (position > 10 && lc_force < 0.5*force) {
          writeToMatrix(force);
          next_state(CONTRACTION);
      }

      break;

    case CONTRACTION:

      get_stage();

      if (stage == between) {
        if (micros()-stage_time > 2000000 && spot_flag == false) spot_check();
      } else {
        if (spot_flag == true) {
          force = 0.95*force_before_spot;
          spot_flag = false;
        }
      }

      if (stage == above) next_state(INFLECTION);

      if (dir_disp < -10) {   
        next_state(RETRACTION);
      }

      if (stage == below) {
        static unsigned long INFLECTION_timer = millis();
        if (millis() - INFLECTION_timer > 1000) {
          INFLECTION_timer = millis();
          next_state(INFLECTION);
        }
      }

      break;

    case INFLECTION:

      get_stage();
      lc_force = LoadCell_val();

      static unsigned long BEGIN_return_timer = millis();

      if (lc_force > 3+1.86) {
        position = 0;
        dir_disp = 0;
        stage = below;
        if (millis() - BEGIN_return_timer > 5000) {
          BEGIN_return_timer = millis();
          WAIT_state();
        }
      } else {
        BEGIN_return_timer = millis();
        if (stage == below && dir_disp > 0) next_state(CONTRACTION);
        if (stage == above && dir_disp < 0) next_state(RETRACTION);

        if (stage == between && dir_disp > 0) next_state(CONTRACTION);
        if (stage == between && dir_disp < 0) next_state(RETRACTION);
      }

      break;

    case RETRACTION:

      get_stage();

      if (stage == between) {
        if (micros()-stage_time > 2000000 && spot_flag == false) spot_check();
      } else {
        if (spot_flag == true) {
          force = 0.95*force_before_spot;
          spot_flag = false;
        }
      }

      if (stage == below) next_state(INFLECTION);

      if (dir_disp > 10) {   
        next_state(CONTRACTION);
      }

      break;

    case CONSTANT:

        if (knob_select == AUTO) {
          writeToMatrix(5555);
          if (millis()-knob_timer > 3000) next_state(WAIT);
        } else {
          force = knob_select;
          pwm_val = force_to_pwm(force);
          analogWrite(pwmPIN, pwm_val);
        }

        if (position > 10 && contraction_start == false) {
          writeToMatrix(force);
          contraction_start = true;
        }

        get_stage();
        lc_force = LoadCell_val();
        static unsigned long WAIT_return_timer = millis();

        if (lc_force > 3+1.86 && previous_stage != below) {
          if (millis() - WAIT_return_timer > 5000) {
            WAIT_return_timer = millis();
            WAIT_state();
          }
        } else {
          WAIT_return_timer = millis();
        }

      break;

  }

  if (state != WAIT && state != CONSTANT) loopLogic();

  printOut();

}

void loopLogic() {

  lc_force = LoadCell_val();
  if (lc_force > 1+1.86) position = 0;

  if (force != previous_force && state != BEGIN && state != SELECT) {
    writeToMatrix(force);
    previous_force = force;
  }

  if (stage == above) {
    pwm_val = force_to_pwm(0.9*force);
  } else {
    pwm_val = force_to_pwm(force);  
  }

  analogWrite(pwmPIN, pwm_val);

  velocity = 0;

  if (state != BEGIN) {
    if (micros() - timer_10th_sec > 100000) {
      newVel(position);    
      timer_10th_sec = micros();
    }

    vars_initialized = false;
  }

  if (millis()-pos_timer < 10000) {
    pos_avg += position;
    pos_counter++;
  } else {
    pos_avg = pos_avg / pos_counter;
    if (pos_avg == (double)position && position > 10) {
      next_state(WAIT);
    }
    pos_avg = 0;
    pos_counter = 0;
    pos_timer = millis();
  }

  (loopCounter > 10000) ? loopCounter = 0 : loopCounter++;

  time_last = millis();

}

double LoadCell_val() {

  Wire.requestFrom(4, 2);
  byte a = Wire.read();
  byte b = Wire.read();
  int c = a << 8 | b;

  double x = (double)c/100;

  return 0.9893852*x + 1.852207;

}

void scale_tare() {

  byte tare = 1;
  Wire.beginTransmission(4);
  Wire.write(tare);
  Wire.endTransmission();

  delay(1);

}

void Reset() {

  stage = below;
  position = 0;
  force = maxForce;
  baselineForce = maxForce;
  lc_force = 0;
  weight_select = 0;
  weight_select_timer = millis();
  weight_select_count = 0;
  velocity_avg = 0;
  disp_tol = 0;
  voltage = 0;
  pwm_val = 0;
  pos_array[20] = {0};
  time_array[20] = {0};
  vel_2s = 0;
  timer_10th_sec = 0;
  timer_2_sec = 0;
  state = WAIT;
  stage = below;
  previous_stage = below;
  stage_time = 0;
  previous_stage_time = 0;
  force_before_spot = force;
  spot_flag = false;
  new_force = force;
  rad = 0;
  BEGIN_timer = millis();
  loopCounter = 0;
  writeToMatrix(1111);  
  matrix_val = 1111;
  knob_select = AUTO;
  knob_timer = millis();
  contraction_start = false;
  analogWrite(pwmPIN, 0);

  aFlag = 0;
  bFlag = 0;
  cFlag = 0;
  dFlag = 0;
  reading = 0;
  reading2 = 0;

  vars_initialized = true;
}

void WAIT_state() {
  state = WAIT;
  WAIT_timer = millis();
}

void printOut() {

  Serial.print("State: ");
  printState();
  Serial.print("  Stage: "); 
  printStage();
  Serial.print("  Force: ");
  Serial.print(force);
  Serial.print("  t: ");
  Serial.print(millis() - time_last);
  Serial.print("  LCell: ");
  Serial.print(lc_force);

    Serial.print("  pwm: ");
    Serial.print(pwm_val);
    Serial.print("  pos: ");
    Serial.print(position);

  Serial.println("");

}

void printState() {
  if (state == WAIT) Serial.print("WAIT");
  if (state == SELECT) Serial.print("SELECT");
  if (state == BEGIN) Serial.print("BEGIN");
  if (state == CONTRACTION) Serial.print("CONTRACTION");
  if (state == INFLECTION) Serial.print("INFLECTION");
  if (state == RETRACTION) Serial.print("RETRACTION");
  if (state == CONSTANT) Serial.print("CONSTANT");
}
void printStage() {
  if (stage == below) Serial.print("below");
  if (stage == between) Serial.print("between");
  if (stage == above) Serial.print("above");
}

void measure() {

  if (position < 0) position = 0;

  int distDelta = position - positionLast;
  long timeDelta = micros() - timeLast;
  if (timeDelta > 10000000) timeDelta = 10000000;  

  velocity = (double)distDelta*optFactor / (double)timeDelta;

  positionLast = position;
  timeLast = micros();

}

void next_state(const int newstate) {
  prev_prev_state = previous_state;
  previous_state = state;
  state = newstate;
}

void get_stage() {

  int new_stage;

  if (position <= lower_threshold) {
    new_stage = below;
  } else if (position <= upper_threshold) {
    new_stage = between;
  } else {
    new_stage = above;
  }

  if (previous_stage == below && new_stage == above) {  
    velocity_avg = (upper_threshold-lower_threshold)*optFactor/(micros()-stage_time);
    if (velocity_avg > 30) force_adjust();
  }

  if (new_stage != stage) {
    previous_stage = stage;
    previous_stage_time = stage_time;
    stage = new_stage;
    stage_time = micros();
  }

  if (position == 0) dir_disp = 0;
  if (position > 200) position = 200;   
}

void spot_check() {

    if (abs(vel_2s) < 15) {
      force_before_spot = force;
      force *= 0.75;
      spot_flag = true;
    }
    if (force < 0) force = 0;

}

void force_adjust() {

    if (velocity_avg < 140) force *= 0.85;
    if (velocity_avg > 230) force *= 1.1;
    if (force > (baselineForce+maxForce)/2) force = (baselineForce+maxForce)/2; 
    if (force < 3) force = 3;

}

void newVel(int pos) {

  unsigned long timeNow = micros();

  for (int i=0; i<19; i++) {
    pos_array[i] = pos_array[i+1];
    time_array[i] = time_array[i+1];
  }
  pos_array[19] = pos;
  time_array[19] = timeNow;

  vel_2s = (double)(pos_array[19]-pos_array[0])*optFactor / ((double)time_array[19]-(double)time_array[0]);
}

int force_to_pwm(double x) {

  double y = -30.51774 + 23.79134*x - 2.017805*pow(x,2) + 0.09511381*pow(x,3) - 0.00196972*pow(x,4) + 0.00001414393*pow(x,5);

  if (y > 210) y = 210;   
  if (y < 0) y = 0; 

  return (int)round(y);

}

void PinAtrigger(){

  reading = PINE & 0x30;
  if(reading == B00110000 && aFlag) {
    position--;
    bFlag = 0;
    aFlag = 0;
    (dir_disp <= 0) ? dir_disp-- : dir_disp = 0;
  }
  else if (reading == B00010000) bFlag = 1;

  measure();
}

void PinBtrigger(){

  reading = PINE & 0x30;
  if (reading == B00110000 && bFlag) {
    position++;
    bFlag = 0;
    aFlag = 0;
    (dir_disp >= 0) ? dir_disp++ : dir_disp = 0;
  }
  else if (reading == B00100000) aFlag = 1;

  measure();
}

void PinCtrigger(){
  reading2 = PIND & 0xC;
  if(reading2 == B00001100 && cFlag) {
    Rotary_Left();
    dFlag = 0;
    cFlag = 0;
  }
  else if (reading2 == B00000100) dFlag = 1;
}

void PinDtrigger(){
  reading2 = PIND & 0xC;
  if (reading2 == B00001100 && dFlag) {
    Rotary_Right();
    dFlag = 0;
    cFlag = 0;
  }
  else if (reading2 == B00001000) cFlag = 1;
}

void init_matrix() {
  for (int i = 0; i < 4; i++) {
    lc.shutdown(i, false);
    lc.setIntensity(i, 2);
    lc.clearDisplay(i);
  }
}

void writeToMatrix(double val) {

  matrix_val = val;

  static byte digitArray2D[10][8] = {
    {B00000000, B00000000, B00111110, B01000001, B01000001, B00111110, B00000000, B00000000},  
    {B00000000, B00000000, B01000010, B01111111, B01000000, B00000000, B00000000, B00000000},  
    {B00000000, B00000000, B01100010, B01010001, B01001001, B01000110, B00000000, B00000000},  
    {B00000000, B00000000, B00100010, B01000001, B01001001, B00110110, B00000000, B00000000},  
    {B00000000, B00000000, B00011000, B00010100, B00010010, B01111111, B00000000, B00000000},  
    {B00000000, B00000000, B00100111, B01000101, B01000101, B00111001, B00000000, B00000000},  
    {B00000000, B00000000, B00111110, B01001001, B01001001, B00110000, B00000000, B00000000},  
    {B00000000, B00000000, B01100001, B00010001, B00001001, B00000111, B00000000, B00000000},  
    {B00000000, B00000000, B00110110, B01001001, B01001001, B00110110, B00000000, B00000000},  
    {B00000000, B00000000, B00000110, B01001001, B01001001, B00111110, B00000000, B00000000}   
  };

  static byte decimal = B01000000;
  static byte empty = B00000000;
  static byte S[8] = {B00000000, B00000000, B00100110, B01001001, B01001001, B00110010, B00000000, B00000000};
  static byte T_A[8] = {B00000001, B00000001, B01111111, B00000001, B00000001, B00000000, B01111110, B00010001};
  static byte A_R[8] = {B00010001, B01111110, B00000000, B00000000, B01111111, B00001001, B00001001, B01110110};
  static byte T[8] = {B00000000, B00000000, B00000001, B00000001, B01111111, B00000001, B00000001, B00000000};
  static byte G[8] = {B00000000, B00000000, B00111110, B01000001, B01001001, B01111010, B00000000, B00000000};
  static byte O_exc[8] = {B00111110, B01000001, B01000001, B00111110, B00000000, B00000000, B01011111, B00000000};
  static byte O[8] = {B00000000, B00000000, B00111110, B01000001, B01000001, B00111110, B00000000, B00000000};
  static byte W[8] = {B00000000, B00111111, B01000000, B00111000, B01000000, B00111111, B00000000, B00000000};
  static byte A[8] = {B00000000, B00000000, B01111110, B00010001, B00010001, B01111110, B00000000, B00000000};
  static byte I[8] = {B00000000, B00000000, B00000000, B01000001, B01111111, B01000001, B00000000, B00000000};
  static byte U[8] = {B00000000, B00000000, B00111111, B01000000, B01000000, B00111111, B00000000, B00000000};
  static byte E[8] = {B00000000, B00000000, B01111111, B01001001, B01001001, B01000001, B00000000, B00000000};
  static byte L[8] = {B00000000, B00000000, B01111111, B01000000, B01000000, B01000000, B00000000, B00000000};
  static byte C[8] = {B00000000, B00000000, B00111110, B01000001, B01000001, B00100010, B00000000, B00000000};
  static byte S_E[8] = {B00000000, B00100110, B01001001, B01001001, B00110010, B00000000, B01111111, B01001001};
  static byte E_L[8] = {B01001001, B01000001, B00000000, B01111111, B01000000, B01000000, B01000000, B00000000};
  static byte E_C[8] = {B01111111, B01001001, B01001001, B01000001, B00000000, B00111110, B01000001, B01000001};
  static byte C_T[8] = {B00100010, B00000000, B00000001, B00000001, B01111111, B00000001, B00000001, B00000000};

  if (val == 1111) {
    for (int i=0; i<4; i++) {
      for (int j=0; j<8; j++) {
          if (i == 0) lc.setColumn(i, j, W[7-j]);
          if (i == 1) lc.setColumn(i, j, A[7-j]);
          if (i == 2) lc.setColumn(i, j, I[7-j]);
          if (i == 3) lc.setColumn(i, j, T[7-j]);
      }
    }
    return;
  }

  if (val == 2222) {
    for (int i=0; i<4; i++) {
      for (int j=0; j<8; j++) {
          if (i == 0) lc.setColumn(i, j, S_E[7-j]);
          if (i == 1) lc.setColumn(i, j, E_L[7-j]);
          if (i == 2) lc.setColumn(i, j, E_C[7-j]);
          if (i == 3) lc.setColumn(i, j, C_T[7-j]);
      }
    }
    return;
  }

  if (val == 3333) {
    for (int i=0; i<4; i++) {
      for (int j=0; j<8; j++) {
          if (i == 0) lc.setColumn(i, j, empty);
          if (i == 1) lc.setColumn(i, j, G[7-j]);
          if (i == 2) lc.setColumn(i, j, O_exc[7-j]);
          if (i == 3) lc.setColumn(i, j, empty);
      }
    }
    return;
  }

  if (val == 4444) {
    for (int i=0; i<4; i++) {
      for (int j=0; j<8; j++) {
          if (i == 0) lc.setColumn(i, j, S[7-j]);
          if (i == 1) lc.setColumn(i, j, E[7-j]);
          if (i == 2) lc.setColumn(i, j, L[7-j]);
          if (i == 3) lc.setColumn(i, j, empty);
      }
    }
    return;
  }

  if (val == 5555) {
    for (int i=0; i<4; i++) {
      for (int j=0; j<8; j++) {
          if (i == 0) lc.setColumn(i, j, A[7-j]);
          if (i == 1) lc.setColumn(i, j, U[7-j]);
          if (i == 2) lc.setColumn(i, j, T[7-j]);
          if (i == 3) lc.setColumn(i, j, O[7-j]);
      }
    }
    return;
  }

  int digit1 = (int)(val/10);
  int digit2 =((int)val)%10;
  int digit3 = ((int)(val*10))%10;
  int digit4 = ((int)(val*100))%10;

  int num[4] = {digit1, digit2, digit3, digit4};

  for (int i=0; i<4; i++) {
    for (int j=0; j<8; j++) {
      if (i == 1 && j == 0) {
        lc.setColumn(1, 0, decimal);
      } else if (i == 0 && num[0] == 0) {
        lc.setColumn(0, j, empty);
      } else {
        lc.setColumn(i, j, digitArray2D[(num[i])][7-j]);
      }
    }
  }

}

void Rotary_Left(){

    if (state == SELECT || state == CONSTANT) {
       if (knob_select > AUTO) {
          knob_select--;
          if (knob_select != AUTO) {  
            writeToMatrix(knob_select);
          } else {
            writeToMatrix(5555);
          }
       }
       knob_timer = millis();
    }
}

void Rotary_Right(){

    if (state == SELECT || state == CONSTANT) {
       if (knob_select < maxForce) {
         knob_select++;
         writeToMatrix(knob_select);
       }
       knob_timer = millis();
    }
}

