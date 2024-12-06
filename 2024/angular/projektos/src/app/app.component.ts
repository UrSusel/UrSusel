import { Component } from '@angular/core';

@Component({
  selector: 'app-root',
  templateUrl: './app.component.html',
  styleUrl: './app.component.css'
})

export class AppComponent {
  title = 'ProjektosT';
  imie: string="Ksawier";
  nazwisko: string="Czorniok";
  klasa: string="5dt";
  wiek: number=17;
  Uczen=new Dane("Jakub","Domański","5ct",19);
  Imiona:string[]=["Jan","Andzej","Ksawier","Nigor","Jemzus"];
  Dni:string[]=["Pon","Wto","Śro","Czwa","Pią","Sob","Nie"];
  
}
export class Dane{
  
  constructor(public imie2:string,public nazwisko2:string,public klasa2:string,public wiek2:number){
   
  }
}